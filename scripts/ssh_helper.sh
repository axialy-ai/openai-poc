#!/bin/bash
# Enhanced SSH Helper Script for Passphrase-Protected Keys
# Usage: ./ssh_helper.sh <command> <host> [additional_args...]

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SSH_KEY_PATH="$HOME/.ssh/ec2_key.pem"
SSH_CONFIG_PATH="$HOME/.ssh/config"
EXPECT_TIMEOUT=60

# Function to setup SSH environment
setup_ssh_environment() {
    echo "Setting up SSH environment..."
    
    # Create SSH directory if it doesn't exist
    mkdir -p ~/.ssh
    chmod 700 ~/.ssh
    
    # Install expect if not available
    if ! command -v expect &> /dev/null; then
        echo "Installing expect..."
        sudo apt-get update -qq
        sudo apt-get install -y expect
    fi
    
    # Verify SSH key exists
    if [ ! -f "$SSH_KEY_PATH" ]; then
        echo "Error: SSH key not found at $SSH_KEY_PATH"
        echo "Make sure EC2_SSH_PRIVATE_KEY secret is properly set"
        exit 1
    fi
    
    # Verify passphrase is set
    if [ -z "$EC2_SSH_PASSPHRASE" ]; then
        echo "Error: EC2_SSH_PASSPHRASE environment variable not set"
        exit 1
    fi
    
    echo "SSH environment setup completed"
}

# Function to create expect script for SSH
create_ssh_expect_script() {
    local script_path="$1"
    
    cat > "$script_path" << 'EOF'
#!/usr/bin/expect -f

set timeout $env(EXPECT_TIMEOUT)
set host [lindex $argv 0]
set command [lindex $argv 1]
set passphrase $env(EC2_SSH_PASSPHRASE)

# Enable logging for debugging
log_user 1
exp_internal 0

# Function to handle SSH connection
proc connect_ssh {host command passphrase} {
    spawn ssh -o StrictHostKeyChecking=no \
              -o UserKnownHostsFile=/dev/null \
              -o ConnectTimeout=30 \
              -o ServerAliveInterval=60 \
              -o ServerAliveCountMax=3 \
              -i ~/.ssh/ec2_key.pem \
              ec2-user@$host \
              $command
    
    expect {
        "Enter passphrase for key" {
            send "$passphrase\r"
            exp_continue
        }
        "Are you sure you want to continue connecting" {
            send "yes\r"
            exp_continue
        }
        "Permission denied" {
            puts stderr "SSH authentication failed"
            exit 1
        }
        "Connection refused" {
            puts stderr "SSH connection refused - server may not be ready"
            exit 2
        }
        "No route to host" {
            puts stderr "No route to host - check network connectivity"
            exit 3
        }
        "$ " {
            # Command completed successfully
        }
        timeout {
            puts stderr "SSH connection timeout after $timeout seconds"
            exit 4
        }
        eof {
            # Command completed
        }
    }
}

# Main execution
if {[llength $argv] < 2} {
    puts stderr "Usage: ssh_expect.exp <host> <command>"
    exit 1
}

connect_ssh $host $command $passphrase

# Wait for command to complete and get exit status
catch wait result
set exit_code [lindex $result 3]
exit $exit_code
EOF
    
    chmod +x "$script_path"
}

# Function to create expect script for SCP
create_scp_expect_script() {
    local script_path="$1"
    
    cat > "$script_path" << 'EOF'
#!/usr/bin/expect -f

set timeout $env(EXPECT_TIMEOUT)
set source [lindex $argv 0]
set dest [lindex $argv 1]
set passphrase $env(EC2_SSH_PASSPHRASE)

# Enable logging for debugging
log_user 1
exp_internal 0

# Function to handle SCP transfer
proc transfer_scp {source dest passphrase} {
    spawn scp -o StrictHostKeyChecking=no \
              -o UserKnownHostsFile=/dev/null \
              -o ConnectTimeout=30 \
              -r \
              -i ~/.ssh/ec2_key.pem \
              $source $dest
    
    expect {
        "Enter passphrase for key" {
            send "$passphrase\r"
            exp_continue
        }
        "Are you sure you want to continue connecting" {
            send "yes\r"
            exp_continue
        }
        "Permission denied" {
            puts stderr "SCP authentication failed"
            exit 1
        }
        "Connection refused" {
            puts stderr "SCP connection refused - server may not be ready"
            exit 2
        }
        "No such file or directory" {
            puts stderr "Source file or destination directory not found"
            exit 3
        }
        "100%" {
            # File transfer in progress
            exp_continue
        }
        timeout {
            puts stderr "SCP transfer timeout after $timeout seconds"
            exit 4
        }
        eof {
            # Transfer completed
        }
    }
}

# Main execution
if {[llength $argv] < 2} {
    puts stderr "Usage: scp_expect.exp <source> <dest>"
    exit 1
}

transfer_scp $source $dest $passphrase

# Wait for transfer to complete and get exit status
catch wait result
set exit_code [lindex $result 3]
exit $exit_code
EOF
    
    chmod +x "$script_path"
}

# Function to test SSH connectivity
test_ssh_connection() {
    local host="$1"
    local max_attempts="${2:-20}"
    local attempt=1
    
    echo "Testing SSH connectivity to $host..."
    
    while [ $attempt -le $max_attempts ]; do
        echo "SSH connection test attempt $attempt/$max_attempts..."
        
        if ~/.ssh/ssh_expect.exp "$host" "echo 'SSH connectivity test successful'" 2>/dev/null | grep -q "SSH connectivity test successful"; then
            echo "✓ SSH is ready and responding"
            return 0
        fi
        
        if [ $attempt -eq $max_attempts ]; then
            echo "❌ SSH failed to become available after $max_attempts attempts"
            echo "Last attempt output:"
            ~/.ssh/ssh_expect.exp "$host" "echo 'SSH test'" 2>&1 || true
            return 1
        fi
        
        echo "SSH not ready, waiting 30 seconds..."
        sleep 30
        ((attempt++))
    done
}

# Function to execute SSH command
execute_ssh_command() {
    local host="$1"
    local command="$2"
    
    echo "Executing SSH command on $host..."
    echo "Command: $command"
    
    ~/.ssh/ssh_expect.exp "$host" "$command"
}

# Function to transfer files via SCP
transfer_files() {
    local source="$1"
    local dest="$2"
    
    echo "Transferring files via SCP..."
    echo "Source: $source"
    echo "Destination: $dest"
    
    ~/.ssh/scp_expect.exp "$source" "$dest"
}

# Main script logic
main() {
    local command="$1"
    local host="$2"
    
    # Set environment variables
    export EXPECT_TIMEOUT="$EXPECT_TIMEOUT"
    
    case "$command" in
        "setup")
            setup_ssh_environment
            create_ssh_expect_script "$HOME/.ssh/ssh_expect.exp"
            create_scp_expect_script "$HOME/.ssh/scp_expect.exp"
            echo "SSH helper setup completed"
            ;;
        "test")
            if [ -z "$host" ]; then
                echo "Error: Host parameter required for test command"
                exit 1
            fi
            test_ssh_connection "$host" "${3:-20}"
            ;;
        "ssh")
            if [ -z "$host" ] || [ -z "$3" ]; then
                echo "Error: Host and command parameters required for ssh command"
                exit 1
            fi
            execute_ssh_command "$host" "$3"
            ;;
        "scp")
            if [ -z "$host" ] || [ -z "$3" ]; then
                echo "Error: Source and destination parameters required for scp command"
                exit 1
            fi
            transfer_files "$host" "$3"
            ;;
        *)
            echo "Usage: $0 {setup|test|ssh|scp} <host> [additional_args...]"
            echo ""
            echo "Commands:"
            echo "  setup                    - Setup SSH environment and create expect scripts"
            echo "  test <host> [attempts]   - Test SSH connectivity to host"
            echo "  ssh <host> <command>     - Execute SSH command on host"
            echo "  scp <source> <dest>      - Transfer files via SCP"
            echo ""
            echo "Environment variables required:"
            echo "  EC2_SSH_PASSPHRASE      - Passphrase for the SSH private key"
            exit 1
            ;;
    esac
}

# Execute main function with all arguments
main "$@"
