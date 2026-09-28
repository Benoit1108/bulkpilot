declare module '#app' {
  interface PageMeta {
    /** Heading displayed in the app shell header and in the document title. */
    title?: string
    /** Reachable without being signed in. */
    public?: boolean
    /** Only for signed-out visitors (sign-in, registration): signed-in users go home. */
    guestOnly?: boolean
  }
}

export {}
