# Notes

## Installing tools 

winget install Microsoft.PowerShell
winget install Git.Git
winget install GitHub.cli
winget install Microsoft.AzureCLI
winget install Hashicorp.Terraform
winget install jqlang.jq
winget install Microsoft.VisualStudioCode

## AZ CLI Bicep Install

az bicep install

## confirm that az cli is on right subscription
az login
az account show --query "{name:name, id:id, tenant:tenantId}" -o table

## login using gh
gh auth login
