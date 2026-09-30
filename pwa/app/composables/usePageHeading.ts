/**
 * The page's heading when it is not its title: the home page greets the user (« Bonjour Alex »)
 * while the document title stays « Accueil ». The layout shows it in place of the title.
 */
export const PAGE_HEADING_KEY = 'page-heading'

export function usePageHeading(heading: () => string | null): void {
  const shown = useState<string | null>(PAGE_HEADING_KEY, () => null)
  watchEffect(() => {
    shown.value = heading()
  })
  onBeforeUnmount(() => {
    shown.value = null
  })
}
