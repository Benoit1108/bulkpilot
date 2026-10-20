/**
 * The page's heading when it is not its title: the home page greets the user (« Bonjour Alex »)
 * while the document title stays « Accueil ». The layout shows it in place of the title. A detail
 * page that names its record (`named`) also gives that name to the document title, which the
 * route announcer reads out.
 */
export const PAGE_HEADING_KEY = 'page-heading'
const OWNER_KEY = 'page-heading-owner'
let pages = 0

export function usePageHeading(heading: () => string | null, options: { named?: boolean } = {}): void {
  const shown = useState<string | null>(PAGE_HEADING_KEY, () => null)
  const owner = useState<number>(OWNER_KEY, () => 0)
  const me = ++pages
  watchEffect(() => {
    shown.value = heading()
    owner.value = me
  })
  // The next page sets its heading before this one unmounts: only clear a heading still ours.
  onBeforeUnmount(() => {
    if (owner.value === me) shown.value = null
  })
  if (options.named) {
    const route = useRoute()
    useHead({ title: () => heading() ?? route.meta.title })
  }
}
