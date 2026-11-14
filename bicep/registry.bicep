param location string = resourceGroup().location
param registryName string = 'acrplatlab${uniqueString(resourceGroup().id)}'

resource acr 'Microsoft.ContainerRegistry/registries@2023-07-01' = {
  name: registryName
  location: location
  sku: { name: 'Basic' }
  properties: { adminUserEnabled: false }
}

output registryName string = acr.name
output loginServer string = acr.properties.loginServer
