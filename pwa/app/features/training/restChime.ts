/**
 * The sound at the end of a rest: two short notes, generated (nothing to download). iPhones cannot
 * vibrate from a web page, so the sound is what tells them. The audio is opened while the user
 * acts (the set just validated starts the rest): browsers only let a page play sound after that.
 */
let context: AudioContext | null = null

/** Called as a set is validated, in the tap's own handler (and again as a rest shows, in case). */
export function prepareChime(): void {
  if (typeof AudioContext === 'undefined') return
  context ??= new AudioContext()
  void context.resume().catch(() => {})
}

export function playChime(): void {
  if (!context || context.state !== 'running') return
  const start = context.currentTime
  for (const [offset, frequency] of [[0, 880], [0.18, 1175]] as const) {
    const tone = context.createOscillator()
    const volume = context.createGain()
    tone.frequency.value = frequency
    volume.gain.setValueAtTime(0.0001, start + offset)
    volume.gain.exponentialRampToValueAtTime(0.25, start + offset + 0.02)
    volume.gain.exponentialRampToValueAtTime(0.0001, start + offset + 0.16)
    tone.connect(volume).connect(context.destination)
    tone.start(start + offset)
    tone.stop(start + offset + 0.17)
  }
}
