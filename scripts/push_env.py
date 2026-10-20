#!/usr/bin/env python3
"""Upload the Frama production server .env over SFTP without printing secrets.

Usage:
    python scripts/push_env.py

Reads Backend/.env.server (local-only, gitignored) and writes it to the
server app dir as .env. Output is masked: only file path, byte count, and a
checkbox. Secrets never reach stdout.

APP_KEY handling: the generated key lives ONLY in the server .env (it is
never stored locally). When the local .env.server has an empty APP_KEY, the
current APP_KEY is read from the server .env and carried over into the
upload, so re-pushing env settings can never wipe the key. Regenerate with
`php artisan key:generate --force` on the server only if the key is lost.
"""
from __future__ import annotations

import os
import sys
import tempfile

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


def fetch_server_app_key(client: paramiko.SSHClient) -> str:
    """Read APP_KEY from the server .env without printing it."""
    try:
        _stdin, stdout, _stderr = client.exec_command(
            f"awk -F= '/^APP_KEY=/{{print substr($0, index($0, \"=\") + 1); exit}}' {APP_DIR}/.env"
        )
        stdout.channel.recv_exit_status()
        return stdout.read().decode("utf-8", errors="replace").strip().strip('"').strip("'")
    except Exception:  # noqa: BLE001 - missing key just means "nothing to preserve"
        return ""


def inject_app_key(lines: list[str], key: str) -> list[str]:
    out: list[str] = []
    replaced = False
    for line in lines:
        if line.startswith("APP_KEY="):
            out.append(f"APP_KEY={key}")
            replaced = True
        else:
            out.append(line)
    if not replaced and key:
        out.append(f"APP_KEY={key}")
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

    with open(ENV_LOCAL_PATH, "r", encoding="utf-8") as fh:
        env_lines = fh.read().splitlines()

    local_key = load_env(ENV_LOCAL_PATH).get("APP_KEY", "")

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        client.connect(host, port=port, username=user, password=password, timeout=30)

        if not local_key:
            server_key = fetch_server_app_key(client)
            if server_key:
                env_lines = inject_app_key(env_lines, server_key)
                print("[ok] preserved existing server APP_KEY (value not shown)")
            else:
                print("[warn] no APP_KEY on server either - run: /usr/bin/php artisan key:generate --force")

        body = "\n".join(env_lines) + "\n"
        size = len(body.encode("utf-8"))

        tmp = tempfile.NamedTemporaryFile("w", encoding="utf-8", suffix=".env.upload", delete=False)
        try:
            tmp.write(body)
            tmp.close()
            sftp = client.open_sftp()
            sftp.put(tmp.name, remote_path)
            sftp.chmod(remote_path, 0o600)
            sftp.close()
        finally:
            os.unlink(tmp.name)

        print(f"[ok] uploaded -> {remote_path} ({size} bytes) [passwords not shown]")
        return 0
    except Exception as exc:  # noqa: BLE001 - report and fail loudly
        print(f"[sftp error] {exc}")
        return 1
    finally:
        client.close()


if __name__ == "__main__":
    sys.exit(main())
