#!/usr/bin/env python3
"""Upload the Frama production server .env over SFTP without printing secrets.

Usage:
    python scripts/push_env.py

Reads Backend/.env.server (local-only, gitignored) and writes it to the
server app dir as .env. Output is masked: only file path, byte count, and a
checkbox. Secrets never reach stdout.

First-time setup only: `php artisan key:generate` runs on the server AFTER
this upload (on the empty database) and fills APP_KEY in the server .env.
"""
from __future__ import annotations

import os
import sys

try:
    import paramiko
except ImportError:  # pragma: no cover - developer machine only
    print("paramiko is required: pip install paramiko")
    sys.exit(1)

APP_DIR = "/home/u214605677/domains/frama-api.custospark.com"

SCRIPTS_DIR = os.path.dirname(os.path.abspath(__file__))
ENV_LOCAL_PATH = os.path.join(SCRIPTS_DIR, "..", ".env.server")
ENV_EXAMPLE_PATH = os.path.join(SCRIPTS_DIR, "..", ".env")


def load_env(path: str) -> dict[str, str]:
    out: dict[str, str] = {}
    if not os.path.exists(path):
        return out
    with open(path, "r", encoding="utf-8") as fh:
        for raw in fh:
            line = raw.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, value = line.partition("=")
            value = value.strip()
            if len(value) >= 2 and value[0] == value[-1] and value[0] in ('"', "'"):
                value = value[1:-1]
            out[key.strip()] = value
    return out


def main() -> int:
    if not os.path.exists(ENV_LOCAL_PATH):
        print(f"[missing] {ENV_LOCAL_PATH}")
        return 1

    ssh = load_env(ENV_EXAMPLE_PATH)
    host = ssh.get("SSH_DEPLOY_HOST", "")
    port = int(ssh.get("SSH_DEPLOY_PORT", "22"))
    user = ssh.get("SSH_DEPLOY_USER", "")
    password = ssh.get("SSH_DEPLOY_PASSWORD", "")

    if not host or not user or not password:
        print("SSH_DEPLOY_HOST / _PORT / _USER / _PASSWORD must be set in Backend/.env")
        return 1

    remote_path = f"{APP_DIR}/.env"
    size = os.path.getsize(ENV_LOCAL_PATH)

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        client.connect(host, port=port, username=user, password=password, timeout=30)
        sftp = client.open_sftp()
        sftp.put(ENV_LOCAL_PATH, remote_path)
        sftp.chmod(remote_path, 0o600)
        sftp.close()
        print(f"[ok] uploaded -> {remote_path} ({size} bytes) [passwords not shown]")
        return 0
    except Exception as exc:  # noqa: BLE001 - report and fail loudly
        print(f"[sftp error] {exc}")
        return 1
    finally:
        client.close()


if __name__ == "__main__":
    sys.exit(main())
