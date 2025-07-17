name: AWS Axialy Admin Deployment (Fixed)

on:
  workflow_dispatch:
    inputs:
      instance_identifier:
        description: "EC2 instance identifier"
        required: true
        default: "axialy-admin"
      aws_region:
        description: "AWS region (e.g. us-west-2, us-east-1)"
        default: "us-west-2"
        required: true
      instance_type:
        description: "EC2 instance type"
        default: "t3.micro"
        required: true
      domain_name:
        description: "Optional domain name for admin interface"
        required: false
        default: ""

env:
  AWS_DEFAULT_REGION: ${{ github.event.inputs.aws_region }}
  INSTANCE_IDENTIFIER: ${{ github.event.inputs.instance_identifier }}

jobs:
  prepare:
    runs-on: ubuntu-latest
    name: Prepare AWS Environment
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Configure AWS credentials
      uses: aws-actions/configure-aws-credentials@v4
      with:
        aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
        aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
        aws-region: ${{ github.event.inputs.aws_region }}

    - name: Cleanup existing resources
      run: |
        echo "Cleaning up existing EC2 instances..."
        EXISTING_INSTANCES=$(aws ec2 describe-instances \
          --filters "Name=tag:Name,Values=${{ env.INSTANCE_IDENTIFIER }}" "Name=instance-state-name,Values=running,pending,stopping,stopped" \
          --query 'Reservations[].Instances[].InstanceId' --output text || echo "")
        
        if [ -n "$EXISTING_INSTANCES" ]; then
          echo "Terminating instances: $EXISTING_INSTANCES"
          aws ec2 terminate-instances --instance-ids $EXISTING_INSTANCES || true
          aws ec2 wait instance-terminated --instance-ids $EXISTING_INSTANCES || true
        fi
        
        # Cleanup security groups
        SG_NAME="${{ env.INSTANCE_IDENTIFIER }}-sg"
        SG_ID=$(aws ec2 describe-security-groups --filters "Name=group-name,Values=$SG_NAME" --query 'SecurityGroups[0].GroupId' --output text 2>/dev/null || echo "None")
        if [ "$SG_ID" != "None" ] && [ "$SG_ID" != "" ]; then
          echo "Deleting security group $SG_ID..."
          aws ec2 delete-security-group --group-id "$SG_ID" || true
        fi

  validate_database:
    runs-on: ubuntu-latest
    name: Validate Database Connection
    needs: prepare
    
    steps:
    - name: Install MySQL Client
      run: |
        sudo apt-get update -qq
        sudo apt-get install -y mysql-client-8.0

    - name: Test database connectivity
      env:
        DB_HOST: ${{ secrets.DB_HOST }}
        DB_PORT: ${{ secrets.DB_PORT }}
        DB_USER: ${{ secrets.DB_USER }}
        DB_PASSWORD: ${{ secrets.DB_PASSWORD }}
      run: |
        echo "Testing database connection..."
        
        if [ -z "$DB_HOST" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASSWORD" ]; then
          echo "ERROR: Missing database credentials in GitHub secrets"
          exit 1
        fi
        
        # Test connection to both databases
        for db in axialy_admin axialy_ui; do
          echo "Testing connection to $db database..."
          if mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" \
              --ssl-mode=REQUIRED \
              --connect-timeout=10 \
              -e "USE $db; SELECT 1;" 2>/dev/null; then
            echo "✓ Connection to $db successful"
          else
            echo "ERROR: Cannot connect to $db database"
            exit 1
          fi
        done

  deploy:
    runs-on: ubuntu-latest
    name: Deploy EC2 and Configure Admin Application
    needs: [prepare, validate_database]
    outputs:
      instance_id: ${{ steps.deploy_ec2.outputs.instance_id }}
      instance_ip: ${{ steps.deploy_ec2.outputs.instance_ip }}
      admin_url: ${{ steps.deploy_ec2.outputs.admin_url }}

    steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Configure AWS credentials
      uses: aws-actions/configure-aws-credentials@v4
      with:
        aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
        aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
        aws-region: ${{ github.event.inputs.aws_region }}

    - name: Setup Terraform
      uses: hashicorp/setup-terraform@v3
      with:
        terraform_version: 1.6.6
        terraform_wrapper: false

    - name: Terraform Init
      working-directory: infra/aws_admin
      run: terraform init

    - name: Terraform Apply
      id: deploy_ec2
      working-directory: infra/aws_admin
      run: |
        terraform apply -auto-approve \
          -var="instance_identifier=${{ env.INSTANCE_IDENTIFIER }}" \
          -var="aws_region=${{ github.event.inputs.aws_region }}" \
          -var="instance_type=${{ github.event.inputs.instance_type }}" \
          -var="domain_name=${{ github.event.inputs.domain_name }}" \
          -var="key_pair_name=${{ secrets.EC2_KEY_PAIR }}" \
          -var="elastic_ip_allocation_id=${{ secrets.EC2_ELASTIC_IP_ALLOCATION_ID }}" \
          -var="db_host=${{ secrets.DB_HOST }}" \
          -var="db_port=${{ secrets.DB_PORT }}" \
          -var="db_user=${{ secrets.DB_USER }}" \
          -var="db_password=${{ secrets.DB_PASSWORD }}" \
          -var="admin_default_user=${{ secrets.ADMIN_DEFAULT_USER }}" \
          -var="admin_default_email=${{ secrets.ADMIN_DEFAULT_EMAIL }}" \
          -var="admin_default_password=${{ secrets.ADMIN_DEFAULT_PASSWORD }}" \
          -var="smtp_host=${{ secrets.SMTP_HOST }}" \
          -var="smtp_port=${{ secrets.SMTP_PORT }}" \
          -var="smtp_user=${{ secrets.SMTP_USER }}" \
          -var="smtp_password=${{ secrets.SMTP_PASSWORD }}" \
          -var="smtp_secure=${{ secrets.SMTP_SECURE }}"

        echo "instance_id=$(terraform output -raw instance_id)" >> $GITHUB_OUTPUT
        echo "instance_ip=$(terraform output -raw instance_ip)" >> $GITHUB_OUTPUT
        echo "admin_url=$(terraform output -raw admin_url)" >> $GITHUB_OUTPUT

    - name: Wait for deployment completion
      run: |
        echo "Waiting for EC2 instance to be running..."
        aws ec2 wait instance-running --instance-ids ${{ steps.deploy_ec2.outputs.instance_id }}
        echo "Waiting additional time for user data script to complete..."
        sleep 300

  test_deployment:
    runs-on: ubuntu-latest
    name: Test Application Deployment
    needs: deploy

    steps:
    - name: Test application endpoints
      env:
        INSTANCE_IP: ${{ needs.deploy.outputs.instance_ip }}
      run: |
        echo "Testing application deployment..."
        
        # Wait for services to be ready
        sleep 60
        
        # Test health endpoint
        echo "Testing health endpoint..."
        HEALTH_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 10 --max-time 30 "http://$INSTANCE_IP/health.php" || echo "000")
        echo "Health endpoint: HTTP $HEALTH_STATUS"
        
        # Test login page
        echo "Testing admin login page..."
        LOGIN_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 10 --max-time 30 "http://$INSTANCE_IP/admin_login.php" || echo "000")
        echo "Login page: HTTP $LOGIN_STATUS"
        
        # Test content
        echo "Testing page content..."
        if curl -s "http://$INSTANCE_IP/admin_login.php" | grep -q "Admin Login"; then
          echo "✓ Login page content is loading correctly"
        else
          echo "⚠ Login page content issue detected"
        fi
        
        # Summary
        if [ "$LOGIN_STATUS" = "200" ] && [ "$HEALTH_STATUS" = "200" ]; then
          echo "✅ All tests passed! Application is accessible."
        else
          echo "⚠️ Some tests failed. Manual verification may be needed."
        fi

    - name: Display deployment summary
      env:
        INSTANCE_ID: ${{ needs.deploy.outputs.instance_id }}
        INSTANCE_IP: ${{ needs.deploy.outputs.instance_ip }}
        ADMIN_URL: ${{ needs.deploy.outputs.admin_url }}
      run: |
        echo "=================================================="
        echo "AWS Axialy Admin Deployment Summary"
        echo "=================================================="
        echo "Instance ID: $INSTANCE_ID"
        echo "Public IP: $INSTANCE_IP"
        echo "Region: ${{ github.event.inputs.aws_region }}"
        echo "Instance Type: ${{ github.event.inputs.instance_type }}"
        echo "=================================================="
        echo ""
        echo "Access URLs:"
        echo "- Admin Login: http://$INSTANCE_IP/admin_login.php"
        echo "- Health Check: http://$INSTANCE_IP/health.php"
        echo ""
        echo "Initial Setup Instructions:"
        echo "1. Visit: http://$INSTANCE_IP/admin_login.php"
        echo "2. If first time setup, enter 'Casellio' as admin code"
        echo "3. Login with username: caseylide, password: Casellio"
        echo "4. Change default password after first login"
        echo "=================================================="
