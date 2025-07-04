#!/bin/bash

set -euo pipefail

TASK_NAME="dicrawler-di-token-updater-${ENVIRONMENT_NAME}"
CLUSTER_NAME="dicrawler-di-${ENVIRONMENT_NAME}"
RULE_NAME="dicrawler.token-updater.di-${ENVIRONMENT_NAME}"  
SUBNET_IDS='["subnet-05bf11d675f0b55aa","subnet-07e0d5b067dd79de4"]' 
SECURITY_GROUP_IDS='["sg-09d400e0dbe0e8045"]'
ROLE_ARN="arn:aws:iam::385750204895:role/dicrawler.eventbridge.di-${ENVIRONMENT_NAME}"

echo "Getting latest revision ARN for task: $TASK_NAME"
LATEST_ARN=$(aws ecs describe-task-definition \
  --region eu-west-1 \
  --task-definition $TASK_NAME \
  --query "taskDefinition.taskDefinitionArn" \
  --output text)

if [[ -z "$LATEST_ARN" ]]; then
  echo "Failed to get latest task definition ARN."
  exit 1
fi

aws events put-targets \
  --region eu-west-1 \
  --rule "$RULE_NAME" \
  --targets "[{
    \"Id\": \"1\",
    \"Arn\": \"arn:aws:ecs:eu-west-1:385750204895:cluster/$CLUSTER_NAME\",
    \"RoleArn\": \"$ROLE_ARN\",
    \"EcsParameters\": {
      \"TaskDefinitionArn\": \"$LATEST_ARN\",
      \"TaskCount\": 1,
      \"LaunchType\": \"FARGATE\",
      \"NetworkConfiguration\": {
        \"awsvpcConfiguration\": {
          \"Subnets\": $SUBNET_IDS,
          \"SecurityGroups\": $SECURITY_GROUP_IDS,
          \"AssignPublicIp\": \"DISABLED\"
        }
      }
    }
  }]"

echo "Cron ECS task definition updated and EventBridge rule re-targeted."
