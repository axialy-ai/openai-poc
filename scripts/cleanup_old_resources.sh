#!/usr/bin/env bash
# ultra‑lean wipe of *just* the RDS bits created by this PoC

set -euo pipefail
DB_ID="${1:-axialy-db}"
REGION="${2:-us-east-1}"
export AWS_DEFAULT_REGION="$REGION"

echo "🔥 Purging $DB_ID (region $REGION)…"

aws rds delete-db-instance \
    --db-instance-identifier "$DB_ID" \
    --skip-final-snapshot   \
    --delete-automated-backups 2>/dev/null || true
aws rds wait db-instance-deleted --db-instance-identifier "$DB_ID" || true

aws db-subnet-groups delete-db-subnet-group \
    --db-subnet-group-name "${DB_ID}-subnet" 2>/dev/null || true

aws rds delete-db-parameter-group \
    --db-parameter-group-name "${DB_ID}-params" 2>/dev/null || true

for lg in error general slowquery; do
  aws logs delete-log-group \
      --log-group-name "/aws/rds/instance/${DB_ID}/${lg}" 2>/dev/null || true
done

aws ec2 delete-security-group \
    --group-name "${DB_ID}-sg" 2>/dev/null || true

echo "✅ done"
