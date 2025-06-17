#!/bin/bash

DEPLOYMENT_STATUS=$(aws --region=eu-west-1 ecs describe-services --cluster dicrawler-di-${ENVIRONMENT_NAME} --service dicrawler-di-${ENVIRONMENT_NAME} | jq .services[0].deployments[0])
echo ${DEPLOYMENT_STATUS}

if [[ $(echo $DEPLOYMENT_STATUS | jq .status) != '"PRIMARY"' ]]
then
  echo "Couldn't determine latest deployment. Should have been in state \"PRIMARY\", is $(echo $DEPLOYMENT_STATUS | jq .status)"
  exit 2
fi

# Wait until state changes
# Transition to a failed state happens after 10 unsuccessful tries,
# see https://aws.amazon.com/blogs/containers/announcing-amazon-ecs-deployment-circuit-breaker/
while [[ $(echo $DEPLOYMENT_STATUS | jq .rolloutState) == '"IN_PROGRESS"' ]]
do
  echo "Waiting for deployment to finish ..."
  sleep 10
  DEPLOYMENT_STATUS=$(aws --region=eu-west-1 ecs describe-services --cluster dicrawler-di-${ENVIRONMENT_NAME} --service dicrawler-di-${ENVIRONMENT_NAME} | jq .services[0].deployments[0])
done

if [[ $(echo $DEPLOYMENT_STATUS | jq .rolloutState) == '"COMPLETED"' ]]
then
  DEPLOYED_VERSION=$(echo $DEPLOYMENT_STATUS | jq -r '.taskDefinition | split(":") | last')
  # If no revision is specified for task definition (i.e <family-name>:<revision>), it gets the latest.
  LATEST_VERSION=$(aws --region=eu-west-1 ecs describe-task-definition --task-definition dicrawler-di-${ENVIRONMENT_NAME} | jq -r .taskDefinition.revision)

  if [[ $DEPLOYED_VERSION == $LATEST_VERSION ]]
  then
    echo "Deployment completed successfully!"
    exit 0
  else
    echo "Deployment failed and was rolled back!"
    echo "Deployed version: $DEPLOYED_VERSION"
    echo "Latest version: $DEPLOYED_VERSION"
    exit 1
  fi
elif [[ $(echo $DEPLOYMENT_STATUS | jq .rolloutState) == '"FAILED"' ]]
then
  echo "Deployment failed! Current state unstable! Please investigate!"
  echo "Reason: $(echo $DEPLOYMENT_STATUS | jq .rolloutStateReason)"
  exit 1
else
  echo "Unknown deployment status encountered: $(echo $DEPLOYMENT_STATUS | jq .rolloutState)"
  echo "Reason: $(echo $DEPLOYMENT_STATUS | jq .rolloutStateReason)"
  exit 2
fi
