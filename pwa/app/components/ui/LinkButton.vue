<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'
import { buttonClass, type ButtonVariant } from './classes'

/** A link that looks like a button: leads to a creation form or another page, never acts. */
withDefaults(defineProps<{ to: RouteLocationRaw, variant?: ButtonVariant }>(), { variant: 'primary' })
</script>

<template>
  <NuxtLink
    :to="to"
    class="self-start"
    :class="buttonClass(variant)"
  >
    <slot />
  </NuxtLink>
</template>
