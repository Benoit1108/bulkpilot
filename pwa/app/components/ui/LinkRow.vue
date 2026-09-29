<template>
  <!-- Links under a form ("Mot de passe oublié", "Créer un compte"): spread on one line, wrapped when narrow. -->
  <p class="flex flex-wrap justify-between gap-3 text-sm [&_a]:inline-flex [&_a]:min-h-tap [&_a]:items-center [&_a]:font-semibold [&_a]:text-accent-ink">
    <slot />
  </p>
</template>
