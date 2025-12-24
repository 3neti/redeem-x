<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'
import HeadingSmall from '@/components/HeadingSmall.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { AlertCircle, Copy, Trash2, Key } from 'lucide-vue-next'
import apiTokens from '@/actions/App/Http/Controllers/Settings/ApiTokenController'
import { type BreadcrumbItem } from '@/types'

interface Token {
  id: number
  name: string
  abilities: string[]
  last_used_at: string | null
  expires_at: string | null
  created_at: string
}

interface Props {
  tokens: Token[]
  availableAbilities: { value: string; label: string; description: string }[]
  plainTextToken?: string
}

const props = defineProps<Props>()

const breadcrumbItems: BreadcrumbItem[] = [
  {
    title: 'API Tokens',
    href: apiTokens.index.url(),
  },
]

const showingToken = computed(() => !!props.plainTextToken)
const copiedToken = ref(false)

const form = useForm({
  name: '',
  abilities: props.availableAbilities.map(a => a.value), // All checked by default
  expires_in_days: 90,
})

const createToken = () => {
  form.post(apiTokens.store.url(), {
    onSuccess: () => {
      form.reset()
    },
  })
}

const deleteToken = (tokenId: number) => {
  if (confirm('Are you sure you want to delete this token? This action cannot be undone.')) {
    router.delete(apiTokens.destroy(tokenId).url, {
      preserveScroll: true,
    })
  }
}

const removeAllTokens = () => {
  if (confirm(`Are you sure you want to delete ALL ${props.tokens.length} tokens? This action cannot be undone and will revoke access for all applications using these tokens.`)) {
    router.delete(apiTokens.destroyAll.url())
  }
}

const copyToken = async () => {
  if (props.plainTextToken) {
    await navigator.clipboard.writeText(props.plainTextToken)
    copiedToken.value = true
    setTimeout(() => {
      copiedToken.value = false
    }, 2000)
  }
}

const closeTokenModal = () => {
  // Just navigate away - showingToken will be false on the next page load
  router.visit(apiTokens.index.url())
}

const toggleAbility = (ability: string) => {
  const index = form.abilities.indexOf(ability)
  if (index > -1) {
    form.abilities.splice(index, 1)
  } else {
    form.abilities.push(ability)
  }
}

const checkAll = () => {
  form.abilities = props.availableAbilities.map(a => a.value)
}

const uncheckAll = () => {
  form.abilities = []
}

const formatDate = (date: string | null) => {
  if (!date) return 'Never'
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const isExpired = (expiresAt: string | null) => {
  if (!expiresAt) return false
  return new Date(expiresAt) < new Date()
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="API Tokens" />

    <SettingsLayout>
      <div class="flex flex-col space-y-6">
        <HeadingSmall
          title="API Tokens"
          description="Manage API tokens for accessing your account via the API"
        />

        <!-- Show new token (only once) -->
        <Card v-if="showingToken && props.plainTextToken" class="border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950/50">
          <CardHeader>
            <div class="flex items-start justify-between">
              <div class="flex items-center gap-2">
                <Key class="h-5 w-5 text-green-600 dark:text-green-500" />
                <CardTitle class="text-base">Token Created Successfully</CardTitle>
              </div>
              <Button variant="ghost" size="sm" @click="closeTokenModal">×</Button>
            </div>
            <CardDescription>
              Please copy your token now. For security reasons, it won't be shown again.
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="flex items-center gap-2">
              <code class="flex-1 rounded bg-white p-3 text-sm dark:bg-gray-900 font-mono overflow-x-auto">{{ props.plainTextToken }}</code>
              <Button @click="copyToken" size="sm" variant="outline">
                <Copy class="h-4 w-4" />
                {{ copiedToken ? 'Copied!' : 'Copy' }}
              </Button>
            </div>
            <p class="text-xs text-muted-foreground">
              Store this token securely. Anyone with this token can access your account via the API.
            </p>
          </CardContent>
        </Card>

        <!-- Create new token form -->
        <Card>
          <CardHeader>
            <CardTitle>Create New Token</CardTitle>
            <CardDescription>
              Generate a new API token with specific permissions
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form @submit.prevent="createToken" class="space-y-6">
              <!-- Token Name -->
              <div class="grid gap-2">
                <Label for="name">Token Name</Label>
                <Input
                  id="name"
                  v-model="form.name"
                  placeholder="My Application"
                  required
                />
                <p class="text-xs text-muted-foreground">
                  A descriptive name to help you identify this token later
                </p>
              </div>

              <!-- Abilities/Permissions -->
              <div class="grid gap-3">
                <div class="flex items-center justify-between">
                  <Label>Permissions</Label>
                  <div class="flex gap-2">
                    <Button type="button" variant="outline" size="sm" @click="checkAll">
                      Check All
                    </Button>
                    <Button type="button" variant="outline" size="sm" @click="uncheckAll">
                      Uncheck All
                    </Button>
                  </div>
                </div>
                <div class="space-y-2">
                  <div
                    v-for="ability in availableAbilities"
                    :key="ability.value"
                    class="flex items-start space-x-3 rounded-lg border p-3 hover:bg-muted/50"
                  >
                    <input
                      type="checkbox"
                      :id="`ability-${ability.value}`"
                      :value="ability.value"
                      :checked="form.abilities.includes(ability.value)"
                      @change="toggleAbility(ability.value)"
                      class="mt-1 h-4 w-4 rounded border-gray-300"
                    />
                    <div class="flex-1">
                      <Label :for="`ability-${ability.value}`" class="cursor-pointer font-medium">
                        {{ ability.label }}
                      </Label>
                      <p class="text-xs text-muted-foreground">{{ ability.description }}</p>
                    </div>
                  </div>
                </div>
                <p class="text-xs text-muted-foreground">
                  Select specific API permissions for this token
                </p>
              </div>

              <!-- Expiration -->
              <div class="grid gap-2">
                <Label for="expires_in_days">Expires In</Label>
                <select
                  id="expires_in_days"
                  v-model="form.expires_in_days"
                  class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                  required
                >
                  <option :value="30">30 days</option>
                  <option :value="60">60 days</option>
                  <option :value="90">90 days (recommended)</option>
                  <option :value="180">180 days</option>
                  <option :value="365">1 year</option>
                  <option :value="null">Never (not recommended)</option>
                </select>
                <p class="text-xs text-muted-foreground">
                  Tokens should expire regularly for security. You can always create a new one.
                </p>
              </div>

              <Button type="submit" :disabled="form.processing || form.abilities.length === 0">
                Create Token
              </Button>
            </form>
          </CardContent>
        </Card>

        <!-- Existing tokens -->
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">Your Tokens</h3>
            <Button
              v-if="tokens.length > 0"
              variant="destructive"
              size="sm"
              @click="removeAllTokens"
            >
              <Trash2 class="h-4 w-4 mr-2" />
              Remove All Tokens
            </Button>
          </div>

          <div v-if="tokens.length === 0" class="rounded-lg border border-dashed p-8 text-center">
            <Key class="mx-auto h-12 w-12 text-muted-foreground" />
            <h3 class="mt-4 text-lg font-semibold">No tokens yet</h3>
            <p class="mt-2 text-sm text-muted-foreground">
              Create your first API token to get started
            </p>
          </div>

          <Card v-for="token in tokens" :key="token.id" :class="{ 'opacity-50': isExpired(token.expires_at) }">
            <CardContent class="pt-6">
              <div class="flex items-start justify-between">
                <div class="flex-1 space-y-3">
                  <div class="flex items-center gap-2">
                    <h4 class="font-semibold">{{ token.name }}</h4>
                    <Badge v-if="isExpired(token.expires_at)" variant="destructive">Expired</Badge>
                  </div>

                  <div class="flex flex-wrap gap-1">
                    <Badge v-for="ability in token.abilities" :key="ability" variant="secondary" class="text-xs">
                      {{ ability }}
                    </Badge>
                    <Badge v-if="token.abilities.length === 0" variant="outline" class="text-xs">
                      No permissions
                    </Badge>
                  </div>

                  <div class="grid grid-cols-2 gap-4 text-sm text-muted-foreground">
                    <div>
                      <span class="font-medium">Created:</span> {{ formatDate(token.created_at) }}
                    </div>
                    <div>
                      <span class="font-medium">Last used:</span> {{ formatDate(token.last_used_at) }}
                    </div>
                    <div>
                      <span class="font-medium">Expires:</span>
                      <span :class="{ 'text-red-600': isExpired(token.expires_at) }">
                        {{ token.expires_at ? formatDate(token.expires_at) : 'Never' }}
                      </span>
                    </div>
                  </div>
                </div>

                <Button
                  variant="ghost"
                  size="sm"
                  @click="deleteToken(token.id)"
                  class="text-red-600 hover:text-red-700 hover:bg-red-50"
                >
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Security notice -->
        <Card class="border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/50">
          <CardHeader>
            <div class="flex items-start gap-3">
              <AlertCircle class="h-5 w-5 text-amber-600 dark:text-amber-500" />
              <div>
                <CardTitle class="text-base">Security Best Practices</CardTitle>
                <CardDescription class="mt-2">
                  Keep your API tokens secure to protect your account
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
              <li>Never commit tokens to version control or share them publicly</li>
              <li>Use environment variables to store tokens in your applications</li>
              <li>Create separate tokens for different applications</li>
              <li>Use the minimum required permissions for each token</li>
              <li>Rotate tokens regularly and delete unused ones</li>
              <li>If a token is compromised, revoke it immediately</li>
            </ul>
          </CardContent>
        </Card>
      </div>
    </SettingsLayout>
  </AppLayout>
</template>
