// Registers every feature's error messages once, before any page renders.
import '~/features/identity/messages'
import '~/features/nutrition/messages'
import '~/features/training/messages'
import '~/features/progression/messages'
import '~/features/coach/messages'

export default defineNuxtPlugin(() => {})
