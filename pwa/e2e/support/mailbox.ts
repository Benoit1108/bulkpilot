import { expect, type APIRequestContext } from '@playwright/test'

const mailpit = process.env.MAILPIT_URL ?? 'http://localhost:8025'

interface MailpitSummary { ID: string }

/**
 * Last link received by `to` whose path matches, read from Mailpit (the stack's SMTP catcher).
 */
export async function linkFromLastEmail(request: APIRequestContext, to: string, path: string): Promise<string> {
  let link: string | undefined
  await expect.poll(async () => {
    const search = await request.get(`${mailpit}/api/v1/search`, { params: { query: `to:"${to}"`, limit: '1' } })
    const { messages } = await search.json() as { messages: MailpitSummary[] }
    if (!messages[0]) return undefined
    const message = await (await request.get(`${mailpit}/api/v1/message/${messages[0].ID}`)).json() as { Text: string }
    link = message.Text.match(new RegExp(`https://\\S+${path}\\?token=\\S+`))?.[0]
    return link
  }, { message: `email with a ${path} link for ${to}` }).toBeTruthy()

  return link!
}
