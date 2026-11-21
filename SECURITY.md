# Security

Report security issues privately to **mail@kreativitat.com** or use GitHub's
private vulnerability reporting when available. Do not include credentials,
customer records, internal logs, or private server details in public issues.

Security fixes target the latest maintained KreaWoo release. Keep Dolibarr, PHP,
WooCommerce, and Composer dependencies on supported, patched versions. Review
dependency alerts before installing or upgrading a production system.

## Before pushing

Install [Gitleaks 8.30.1](https://github.com/gitleaks/gitleaks/releases/tag/v8.30.1)
from its official release and verify the published archive checksum. Enable the
repository's local pre-push checks:

```sh
git config core.hooksPath .githooks
```

If the scanner is outside `PATH`, set its absolute path with
`git config gitleaks.path /absolute/path/to/gitleaks`. The hook checks the working
tree, compressed archives, and all locally reachable Git history. It fails if the
scanner is missing or a secret is detected. Do not bypass a real finding.

The publication guard also rejects tracked archives, logs, credentials, private
directories, deployment notes, and common business-report files in every checked
commit, including merge resolutions and files deleted later. SQL installation
files remain allowed in `sql/`. Release ZIPs belong in separately reviewed release
assets, rather than Git history.

GitHub Actions repeats the source/archive check and scans newly introduced commits,
including secrets removed by a later commit in the same push. Run the Security
workflow manually with `full_history` enabled before a public release. The scanner
extends its default rules with a WooCommerce consumer-credential rule; inline
`gitleaks:allow` comments cannot suppress findings. Scan logs are redacted and
reports are not uploaded.

## Release contents

Publish module source, schema, translations, dependencies' manifests and lockfiles,
and required license notices. Exclude runtime logs, credentials, database exports,
backups, internal deployment notes, and local assistant configuration. Review
compressed archives recursively; file ignore rules do not sanitize an archive.

This public repository starts with an audited source snapshot. Legacy release ZIPs
and private Git history are excluded. Keeping the source snapshot clean does not
sanitize any older private clone: never push old branches or tags into this
repository or merge unrelated private history.

If sensitive data is committed, keep the repository private where applicable,
revoke or rotate affected credentials through the service's approved workflow,
and coordinate history cleanup. A deletion commit leaves earlier content reachable.
