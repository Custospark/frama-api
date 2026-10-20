#!/usr/bin/env python3
"""Run a command on the Frama production server over SSH.

Credentials come from Backend/.env (SSH_DEPLOY_*) - the same Hostinger account
(u214605677) shared with Academy and Custosell - and are never printed: the
password is read into memory, used by paramiko, and never echoed. Nothing in
this repository contains a deploy secret.

Usage:
    python scripts/ssh_run.py "<command>"
    python scripts/ssh_run.py --timeout 900 "cd /path && /usr/bin/php artisan migrate --force"

The command timeout defaults to 900s because a first deploy runs composer
install and migrations, each of which outlasts a minute; the Custosell
helper's 60s cap aborts those mid-write.
"""
from __future__ import annotations

import argparse
import os
import sys

try:
    import paramiko
except ImportError:  # pragma: no cover - developer machine only
    print("paramiko is required: pip install paramiko")
    sys.exit(1)

DEFAULT_TIMEOUT = 900


def load_env() -> dict[str, str]:
    """Read SSH_DEPLOY_* from Backend/.env without disturbing anything else."""
    env_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", ".env")
    values: dict[str, str] = {}
    if not os.path.exists(env_path):
        return values
    with open(env_path, encoding="utf-8", errors="ignore") as handle:
        for line in handle:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, value = line.partition("=")
            key = key.strip()
            if key.startswith("SSH_DEPLOY_"):
                # The server .env may carry CRLF endings; a trailing \r would
                # become part of the password and fail the login.
                values[key] = value.strip().strip('"').strip("'")
    return values


def main() -> int:
    parser = argparse.ArgumentParser(description="Run a command on the deploy server.")
    parser.add_argument("command", help="the shell command to run remotely")
    parser.add_argument(
        "--timeout",
        type=int,
        default=DEFAULT_TIMEOUT,
        help=f"seconds to allow (default {DEFAULT_TIMEOUT})",
    )
    args = parser.parse_args()

    env = load_env()
    host = env.get("SSH_DEPLOY_HOST", "")
    port = int(env.get("SSH_DEPLOY_PORT", "22"))
    user = env.get("SSH_DEPLOY_USER", "")
    password = env.get("SSH_DEPLOY_PASSWORD", "")

    if not host or not user or not password:
        print("SSH_DEPLOY_HOST / _PORT / _USER / _PASSWORD must be set in Backend/.env")
        return 1

    client = paramiko.SSHClient()
    # The account has no known_hosts entry for this host. Hostinger's shared
    # SSH endpoint is pinned in DEPLOYMENT.md; pinning a key we do not have
    # would block the deploy rather than protect it.
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        client.connect(host, port=port, username=user, password=password, timeout=30)
        _, stdout, stderr = client.exec_command(args.command, timeout=args.timeout)

        out = stdout.read().decode("utf-8", errors="replace")
        err = stderr.read().decode("utf-8", errors="replace")
        status = stdout.channel.recv_exit_status()

        if out:
            print(out, end="" if out.endswith("\n") else "\n")
        if err:
            print("[stderr]")
            print(err, end="" if err.endswith("\n") else "\n")
        return status
    except Exception as exc:  # noqa: BLE001 - report and fail loudly
        print(f"[ssh error] {exc}")
        return 1
    finally:
        client.close()


if __name__ == "__main__":
    sys.exit(main())
