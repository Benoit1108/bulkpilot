/**
 * Class lists shared across components: buttons (and links styled as one), chips, text links, form
 * fields. Tailwind reads them from here: each class is written out in full (whole class lists may be
 * joined, a class name is never built from pieces).
 */
export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger'

const BUTTON = 'inline-flex min-h-tap cursor-pointer touch-manipulation items-center justify-center gap-2 rounded-md border px-4 font-semibold no-underline transition-[background-color,border-color,opacity] disabled:cursor-not-allowed disabled:opacity-55 aria-disabled:cursor-not-allowed aria-disabled:opacity-55'

const VARIANTS: Record<ButtonVariant, string> = {
  primary: 'border-transparent bg-accent-fill text-accent-contrast not-disabled:not-aria-disabled:hover:bg-accent-fill-hover',
  secondary: 'border-line bg-surface-raised text-ink not-disabled:not-aria-disabled:hover:border-accent',
  ghost: 'border-transparent bg-transparent text-accent-ink not-disabled:not-aria-disabled:hover:bg-accent-soft',
  /** A destructive action about to happen (a confirm button once armed). */
  danger: 'border-danger bg-transparent text-danger not-disabled:not-aria-disabled:hover:bg-danger/10',
}

export const buttonClass = (variant: ButtonVariant) => `${BUTTON} ${VARIANTS[variant]}`

/**
 * A link in running text or under a list ("Créer un aliment", "Voir le mouvement"), tall enough to
 * tap. It keeps its own width in a column (self-start) so that its label lines up with the text.
 */
export const TEXT_LINK = 'inline-flex min-h-tap min-w-tap items-center justify-center self-start font-semibold text-accent-ink'

/** A one-tap choice (set kind, RIR): a radio hidden inside its label, filled once checked. */
const CHIP_BASE = 'inline-flex min-h-tap min-w-tap cursor-pointer items-center justify-center rounded-md border border-line bg-surface-raised font-semibold has-checked:border-accent-fill has-checked:bg-accent-fill has-checked:text-accent-contrast has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-accent'
export const CHIP = `${CHIP_BASE} px-3`
/** A chip for a figure: square, digits of equal width. */
export const CHIP_NUMBER = `${CHIP_BASE} p-0 tabular-nums`

/**
 * A choice among a few (a period, a portion): a radio hidden in a rounded label, tinted once checked
 * and ticked, so the choice does not rest on colour alone. Positioned, so that the hidden radio stays
 * inside its own pill (a click or a scroll to it lands on the right one).
 */
export const PILL = 'relative inline-flex min-h-tap min-w-tap cursor-pointer items-center justify-center rounded-full border border-line bg-surface-raised px-3 text-sm font-semibold has-checked:border-2 has-checked:border-accent has-checked:bg-accent-soft has-checked:px-[11px] has-checked:text-accent-ink has-checked:before:me-1 has-checked:before:content-["✓"_/_""] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-accent'

/** A table of figures (the values under a chart): a line under each row, figures to the right. */
export const DATA_TABLE = 'mt-2 w-full border-collapse tabular-nums [&_:is(td,th)]:border-b [&_:is(td,th)]:border-line [&_:is(td,th)]:py-1 [&_:is(td,th)]:text-right [&_:is(td,th):first-child]:text-left'

/** A row of a list that is itself a button (a food, an exercise, an entry): full width, a line above. */
export const LIST_ROW = 'flex min-h-tap w-full cursor-pointer flex-col items-start gap-1 border-t border-line bg-transparent px-0 py-2 text-left text-inherit'

export const fieldClass = {
  root: 'flex min-w-0 flex-col gap-1',
  label: 'text-sm font-semibold',
  hint: 'text-sm text-muted',
  error: 'text-sm text-danger',
  /** The bordered box of a field; a red border once invalid. */
  box: 'rounded-md border border-line bg-surface-raised aria-invalid:border-danger',
} as const

/** A round icon button of the page header (search, add, notifications, settings). */
export const HEADER_BUTTON = 'relative grid size-tap cursor-pointer place-items-center rounded-full border border-line bg-surface p-0 text-muted transition-colors hover:border-accent hover:text-ink aria-[current=page]:border-accent aria-[current=page]:bg-accent-soft aria-[current=page]:text-accent-ink'
