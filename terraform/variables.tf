variable "location" {
  type    = string
  default = "eastus2"
}

variable "readers_group_id" {
  type        = string
  description = "Object ID of the platform-readers Entra group"
}

locals {
  tags = {
    owner   = "bobby"
    project = "homelab_01"
    env     = "dev"
  }
}
