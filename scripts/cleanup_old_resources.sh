#!/bin/bash

set -e

DB_INSTANCE_IDENTIFIER="${1:-axialy-database}"
AWS_REGION="${2:-us-west-2}"

echo "Starting cleanup of old Axialy AWS resources..."
echo "Instance Identifier: $DB_INSTANCE_IDENTIFIER"
echo "AWS Region: $AWS_REGION"

export AWS_DEFAULT_REGION="$AWS_REGION"

cleanup_rds_instance() {
    echo "Checking for existing RDS instance..."
    
    if aws rds describe-db-instances --db-instance-identifier "$DB_INSTANCE_IDENTIFIER" >/dev/null 2>&1; then
        echo "Found existing RDS instance $DB_INSTANCE_IDENTIFIER"
        
        echo "Deleting RDS instance..."
        aws rds delete-db-instance \
            --db-instance-identifier "$DB_INSTANCE_IDENTIFIER" \
            --skip-final-snapshot \
            --delete-automated-backups
        
        echo "Waiting for RDS instance to be deleted..."
        aws rds wait db-instance-deleted --db-instance-identifier "$DB_INSTANCE_IDENTIFIER"
        echo "RDS instance deleted successfully"
    else
        echo "No existing RDS instance found"
    fi
}

cleanup_security_groups() {
    echo "Cleaning up security groups..."
    
    SG_NAME="${DB_INSTANCE_IDENTIFIER}-rds-sg"
    SG_ID=$(aws ec2 describe-security-groups --filters "Name=group-name,Values=$SG_NAME" --query 'SecurityGroups[0].GroupId' --output text 2>/dev/null || echo "None")
    
    if [ "$SG_ID" != "None" ] && [ "$SG_ID" != "" ]; then
        echo "Deleting security group $SG_ID..."
        aws ec2 delete-security-group --group-id "$SG_ID" || echo "Security group already deleted or in use"
    fi
}

cleanup_parameter_groups() {
    echo "Cleaning up parameter groups..."
    
    PARAM_GROUP_NAME="${DB_INSTANCE_IDENTIFIER}-params"
    if aws rds describe-db-parameter-groups --db-parameter-group-name "$PARAM_GROUP_NAME" >/dev/null 2>&1; then
        echo "Deleting parameter group $PARAM_GROUP_NAME..."
        aws rds delete-db-parameter-group --db-parameter-group-name "$PARAM_GROUP_NAME" || echo "Parameter group already deleted"
    fi
}

cleanup_subnet_groups() {
    echo "Cleaning up subnet groups..."
    
    SUBNET_GROUP_NAME="${DB_INSTANCE_IDENTIFIER}-subnet-group"
    if aws rds describe-db-subnet-groups --db-subnet-group-name "$SUBNET_GROUP_NAME" >/dev/null 2>&1; then
        echo "Deleting subnet group $SUBNET_GROUP_NAME..."
        aws rds delete-db-subnet-group --db-subnet-group-name "$SUBNET_GROUP_NAME" || echo "Subnet group already deleted"
    fi
}

cleanup_log_groups() {
    echo "Cleaning up CloudWatch log groups..."
    
    LOG_GROUPS=(
        "/aws/rds/instance/${DB_INSTANCE_IDENTIFIER}/error"
    )
    
    for log_group in "${LOG_GROUPS[@]}"; do
        if aws logs describe-log-groups --log-group-name-prefix "$log_group" --query 'logGroups[0].logGroupName' --output text 2>/dev/null | grep -q "$log_group"; then
            echo "Deleting log group $log_group..."
            aws logs delete-log-group --log-group-name "$log_group" || echo "Log group already deleted"
        fi
    done
}

cleanup_iam_roles() {
    echo "Cleaning up IAM roles..."
    
    ROLE_NAME="${DB_INSTANCE_IDENTIFIER}-rds-monitoring-role"
    if aws iam get-role --role-name "$ROLE_NAME" >/dev/null 2>&1; then
        echo "Detaching policies from role $ROLE_NAME..."
        aws iam detach-role-policy --role-name "$ROLE_NAME" --policy-arn "arn:aws:iam::aws:policy/service-role/AmazonRDSEnhancedMonitoringRole" || echo "Policy already detached"
        
        echo "Deleting IAM role $ROLE_NAME..."
        aws iam delete-role --role-name "$ROLE_NAME" || echo "Role already deleted"
    else
        echo "No IAM roles found to cleanup"
    fi
}

cleanup_ec2_instances() {
    echo "Cleaning up temporary EC2 instances..."
    
    INSTANCE_IDS=$(aws ec2 describe-instances \
        --filters "Name=tag:Name,Values=axialy-db-setup" "Name=instance-state-name,Values=running,stopped,stopping" \
        --query 'Reservations[].Instances[].InstanceId' \
        --output text)
    
    if [ -n "$INSTANCE_IDS" ]; then
        echo "Terminating EC2 instances: $INSTANCE_IDS"
        aws ec2 terminate-instances --instance-ids $INSTANCE_IDS
        
        for instance_id in $INSTANCE_IDS; do
            echo "Waiting for instance $instance_id to terminate..."
            aws ec2 wait instance-terminated --instance-ids "$instance_id"
        done
    fi
}

cleanup_temp_security_groups() {
    echo "Cleaning up temporary security groups..."
    
    TEMP_SG_IDS=$(aws ec2 describe-security-groups \
        --filters "Name=group-name,Values=axialy-db-setup-sg" \
        --query 'SecurityGroups[].GroupId' \
        --output text)
    
    if [ -n "$TEMP_SG_IDS" ]; then
        for sg_id in $TEMP_SG_IDS; do
            echo "Deleting temporary security group $sg_id..."
            aws ec2 delete-security-group --group-id "$sg_id" || echo "Security group already deleted"
        done
    fi
}

main() {
    echo "=========================================="
    echo "Axialy AWS Resource Cleanup Script"
    echo "=========================================="
    
    cleanup_rds_instance
    sleep 10
    cleanup_security_groups
    cleanup_parameter_groups
    cleanup_subnet_groups
    cleanup_log_groups
    cleanup_iam_roles
    cleanup_ec2_instances
    cleanup_temp_security_groups
    
    echo "=========================================="
    echo "Cleanup completed successfully!"
    echo "=========================================="
}

if [ "${BASH_SOURCE[0]}" == "${0}" ]; then
    main "$@"
fi
