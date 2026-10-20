<script setup lang="ts">
import { ExternalLink } from 'lucide-vue-next'
import { WGER_IMAGES } from '~/features/training/illustrations'

definePageMeta({ title: 'Crédits et licences' })

const CC_BY_SA = { href: 'https://creativecommons.org/licenses/by-sa/4.0/deed.fr', label: 'CC BY-SA 4.0' }

/** What the app shows that others made, under which licence (the files say it too, next to them). */
const credits = [
  {
    title: 'Dessins des exercices',
    text: 'Everkinetic (Greg Priday), sous licence CC BY-SA 4.0. Modifiés : fond retiré, tracé colorié par l’application.',
    links: [{ href: 'https://github.com/everkinetic/data', label: 'everkinetic/data' }, CC_BY_SA],
  },
  {
    title: 'Images d’exercices wger',
    text: 'Sous licence CC BY-SA 4.0, redimensionnées et converties en WebP.',
    links: [...Object.values(WGER_IMAGES).map(image => ({ href: image.url, label: `${image.work} (${image.author})` })), CC_BY_SA],
  },
  {
    title: 'Silhouette des muscles',
    text: 'Tracés de react-body-highlighter (© 2020 GV79), sous licence MIT.',
    links: [{ href: 'https://github.com/giavinh79/react-body-highlighter', label: 'react-body-highlighter' }, { href: '/credits/react-body-highlighter-LICENSE.txt', label: 'Licence MIT' }],
  },
  {
    title: 'Composition des aliments',
    text: 'Table Ciqual de l’Anses (Licence Ouverte Etalab 2.0) ; produits emballés d’Open Food Facts (ODbL).',
    links: [{ href: 'https://ciqual.anses.fr', label: 'Ciqual' }, { href: 'https://world.openfoodfacts.org', label: 'Open Food Facts' }],
  },
  {
    title: 'Police et icônes',
    text: 'Inter (SIL Open Font License) ; icônes Lucide (ISC).',
    links: [{ href: 'https://rsms.me/inter/', label: 'Inter' }, { href: 'https://lucide.dev', label: 'Lucide' }],
  },
]
</script>

<template>
  <UiPage layout="columns">
    <template #top>
      <ShellBackLink
        to="/reglages"
        label="Réglages"
      />
    </template>
    <UiCard
      v-for="credit in credits"
      :key="credit.title"
      :title="credit.title"
    >
      <p>{{ credit.text }}</p>
      <ul
        class="flex flex-wrap gap-x-4"
        role="list"
      >
        <li
          v-for="link in credit.links"
          :key="link.href"
        >
          <a
            :href="link.href"
            class="inline-flex min-h-tap min-w-tap items-center gap-1 font-semibold text-accent-ink"
            target="_blank"
            rel="noopener noreferrer"
          >{{ link.label }}<ExternalLink
            :size="14"
            aria-hidden="true"
          /><span class="sr-only"> (nouvel onglet)</span></a>
        </li>
      </ul>
    </UiCard>
  </UiPage>
</template>
