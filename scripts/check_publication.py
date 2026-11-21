#!/usr/bin/env python3
"""Reject private artifacts throughout a source publication's commit range."""

# Copyright (C) 2026 Kreativität Works <mail@kreativitat.com>
# SPDX-License-Identifier: GPL-3.0-or-later

import argparse
import json
import re
import subprocess
import sys
from pathlib import PurePosixPath


def is_private_path(path):
    """Return whether a Git path belongs outside the public source repository."""
    parts = tuple(part.lower() for part in PurePosixPath(path).parts)
    name = parts[-1] if parts else ""
    if any(part in {".git", "private", "backups", "logs"} for part in parts):
        return True
    if name in {".ds_store", "auth.json", ".gitlab-ci.yml", "claude.md"}:
        return True
    if name == ".env" or (name.startswith(".env.") and not name.endswith(".example")):
        return True
    if name.startswith("gitleaks-report.") or ".log." in name:
        return True
    if name.endswith((".zip", ".tar", ".gz", ".bz2", ".xz", ".tgz", ".7z", ".rar",
                      ".zst", ".zstd", ".tbz2", ".jar", ".phar", ".bak", ".db",
                      ".log", ".pem", ".key", ".p12", ".pfx", ".dump", ".sqlite",
                      ".sqlite3", ".csv", ".tsv", ".xlsx", ".xls", ".pdf", ".parquet")):
        return True
    if name.endswith(".sql"):
        is_module_schema = len(parts) == 2 and parts[0] == "sql" and re.fullmatch(
            r"llx_(?:c_kreawoo|kreawoo|ecommerce)_[a-z0-9_]+(?:\.key)?\.sql", name
        )
        return not is_module_schema
    return name.startswith("deployment-") and name.endswith(".md")


def introduced_private_paths(revision_range):
    """Inspect root, ordinary, and merge commits without opening their payloads."""
    if not re.fullmatch(r"--all|[0-9a-f]{40}(?:\.\.[0-9a-f]{40})?", revision_range):
        raise ValueError("Expected --all, a commit SHA, or a SHA..SHA range")
    commits = subprocess.check_output(["git", "rev-list", revision_range]).splitlines()
    findings = set()
    for commit in commits:
        paths = subprocess.check_output([
            "git", "diff-tree", "--root", "-m", "--no-commit-id", "--name-only",
            "--no-renames", "--diff-filter=ACMR", "-r", "-z", commit.decode("ascii"),
        ])
        for raw_path in paths.split(b"\0"):
            if raw_path:
                path = raw_path.decode("utf-8", errors="surrogateescape")
                if is_private_path(path):
                    findings.add(path)
    return sorted(findings)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--range", default="--all", dest="revision_range")
    args = parser.parse_args()
    try:
        findings = introduced_private_paths(args.revision_range)
    except (ValueError, subprocess.CalledProcessError) as error:
        print("Publication guard could not inspect the requested history: " + str(error), file=sys.stderr)
        return 2
    for path in findings:
        print("Private publication artifact: " + json.dumps(path), file=sys.stderr)
    if findings:
        return 1
    print("Publication artifact check passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
