#!/bin/bash

# Terraform Import Helper Script for Existing Resources
# This script attempts to import existing AWS resources into Terraform state

set -e

DB_INSTANCE_IDENTIFIER="${1:-axialy-database}"
AWS_REGION="${2:-us-west-2}"

echo "Checking for existing resources to import..."

# Import existing parameter group if it exists
PARAM_GROUP_NAME="${DB_INSTANCE_IDENTIFIER}-params"
if aws rds describe-db-parameter-groups --db-parameter-group-name "$PARAM_GROUP_NAME" >/dev/null 2>&1; then
    echo "Importing existing parameter group: $PARAM_GROUP_NAME"
    terraform import aws_db_parameter_group.axialy_mysql "$PARAM_GROUP_NAME" || true
fi

# Import existing subnet group if it exists
SUBNET_GROUP_NAME="${DB_INSTANCE_IDENTIFIER}-subnet-group"
if aws rds describe-db-subnet-groups --db-subnet-group-name "$SUBNET_GROUP_NAME" >/dev/null 2>&1; then
    echo "Importing existing subnet group: $SUBNET_GROUP_NAME"
    terraform import aws_db_subnet_group.axialy "$SUBNET_GROUP_NAME" || true
fi

# Import existing security group if it exists
SG_ID=$(aws ec2 describe-security-groups --filters "Name=group-name,Values=${DB_INSTANCE_IDENTIFIER}-rds-sg" --query 'SecurityGroups[0].GroupId' --output text 2>/dev/null || echo "None")
if [ "$SG_ID" != "None" ] && [ "$SG_ID" != "" ]; then
    echo "Importing existing security group: $SG_ID"
    terraform import aws_security_group.axialy_rds "$SG_ID" || true
fi

# Import existing log group if it exists
LOG_GROUP="/aws/rds/instance/${DB_INSTANCE_IDENTIFIER}/error"
if aws logs describe-log-groups --log-group-name-prefix "$LOG_GROUP" --query 'logGroups[0].logGroupName' --output text 2>/dev/null | grep -q "$LOG_GROUP"; then
    echo "Importing existing log group: $LOG_GROUP"
    terraform import aws_cloudwatch_log_group.rds_error_log "$LOG_GROUP" || true
fi

# Import existing RDS instance if it exists
if aws rds describe-db-instances --db-instance-identifier "$DB_INSTANCE_IDENTIFIER" >/dev/null 2>&1; then
    echo "Importing existing RDS instance: $DB_INSTANCE_IDENTIFIER"
    terraform import aws_db_instance.axialy "$DB_INSTANCE_IDENTIFIER" || true
fi

echo "Import process completed"
