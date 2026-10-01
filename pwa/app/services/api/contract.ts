/**
 * Compile-time view of the API contract generated from the OpenAPI document (types/api.d.ts):
 * which paths accept which method, and the JSON bodies they take and return.
 */
import type { paths } from '~/types/api'

export type HttpMethod = 'get' | 'post' | 'put' | 'delete'

type OperationOf<P extends keyof paths, M extends HttpMethod> = paths[P] extends { [K in M]: infer O } ? O : never

/** Paths that declare the method. */
export type PathWith<M extends HttpMethod> = {
  [P in keyof paths]: paths[P] extends { [K in M]: object } ? P : never
}[keyof paths]

type Json<C> = C extends { 'application/json': infer B } ? B : never

/** JSON body of the 2xx response (`undefined` for 202/204 without content). */
export type ResponseOf<P extends keyof paths, M extends HttpMethod> = OperationOf<P, M> extends { responses: infer R }
  ? R extends { 200: { content: infer C } } ? Json<C>
    : R extends { 201: { content: infer C } } ? Json<C>
      : R extends { 202: { content: infer C } } ? Json<C>
        : undefined
  : never

export type RequestBodyOf<P extends keyof paths, M extends HttpMethod> = OperationOf<P, M> extends { requestBody: { content: infer C } }
  ? Json<C>
  : OperationOf<P, M> extends { requestBody?: { content: infer C } } ? Json<C> | undefined : undefined

export type PathParamsOf<P extends keyof paths, M extends HttpMethod> = OperationOf<P, M> extends { parameters: { path: infer PP } }
  ? PP
  : undefined

/** Replaces `{name}` segments; values are URI-encoded. */
export function fillPath(template: string, params: Record<string, string | number> | undefined): string {
  return template.replace(/\{(\w+)\}/g, (_, name: string) => {
    const value = params?.[name]
    if (value === undefined) throw new TypeError(`Missing path parameter "${name}" for ${template}`)
    return encodeURIComponent(String(value))
  })
}
