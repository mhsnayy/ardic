import "server-only";
import { cookies } from "next/headers";
import { notFound, redirect } from "next/navigation";
import { EP_COOKIE, PS_COOKIE } from "@/lib/session";
import type { ActionResult, FieldErrors } from "@/lib/action-result";

export type Service = "personnel" | "education";

export function baseUrl(service: Service): string {
  const url =
    service === "personnel"
      ? process.env.PERSONNEL_API_URL || "http://127.0.0.1:8001"
      : process.env.EDUCATION_API_URL || "http://127.0.0.1:8002";
  return url.replace(/\/+$/, "");
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public fieldErrors?: FieldErrors,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export class NetworkError extends Error {
  constructor() {
    super("Sunucuya ulaşılamıyor.");
    this.name = "NetworkError";
  }
}

type Query = Record<string, string | number | undefined | null>;

type Options = { method?: string; query?: Query; body?: unknown };

/** Ham çağrı. Başarısızlıkta ApiError / NetworkError fırlatır. */
export async function apiFetch<T>(service: Service, path: string, opts: Options = {}): Promise<T> {
  const token = (await cookies()).get(service === "personnel" ? PS_COOKIE : EP_COOKIE)?.value;
  if (!token) throw new ApiError(401, "Oturum bulunamadı.");

  const url = new URL(baseUrl(service) + path);
  for (const [k, v] of Object.entries(opts.query ?? {})) {
    if (v !== undefined && v !== null && v !== "") url.searchParams.set(k, String(v));
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
    Authorization: `Bearer ${token}`,
  };
  if (opts.body !== undefined) headers["Content-Type"] = "application/json";

  let res: Response;
  try {
    res = await fetch(url, {
      method: opts.method ?? "GET",
      headers,
      body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
      cache: "no-store",
      signal: AbortSignal.timeout(15000),
    });
  } catch {
    throw new NetworkError();
  }

  if (res.status === 204) return undefined as T;

  let json: unknown = null;
  try {
    json = await res.json();
  } catch {
    json = null;
  }

  if (!res.ok) {
    const body = (json ?? {}) as { message?: string; errors?: FieldErrors };
    throw new ApiError(res.status, body.message || `İstek başarısız oldu (${res.status}).`, body.errors);
  }
  return json as T;
}

/** Server Component okumaları: 401 -> logout, 404 -> notFound, diğerleri error.tsx'e düşer. */
export async function readApi<T>(service: Service, path: string, query?: Query): Promise<T> {
  let failure: ApiError | null = null;
  try {
    return await apiFetch<T>(service, path, { query });
  } catch (e) {
    if (e instanceof ApiError) failure = e;
    else throw e; // ağ hatası -> error.tsx
  }
  if (failure.status === 401) redirect("/logout?expired=1");
  if (failure.status === 404) notFound();
  if (failure.status === 403) throw new Error("Yetkiniz yok.");
  throw new Error(failure.message);
}

/** Server Action sarmalayıcı: hataları ActionResult'a çevirir. */
export async function mutate(fn: () => Promise<string>): Promise<ActionResult> {
  let failure: ApiError | null = null;
  try {
    return { ok: true, message: await fn() };
  } catch (e) {
    if (e instanceof ApiError) failure = e;
    else if (e instanceof NetworkError) return { ok: false, message: "Sunucuya ulaşılamıyor." };
    else throw e;
  }
  if (failure.status === 401) redirect("/logout?expired=1");
  if (failure.status === 403) return { ok: false, message: "Yetkiniz yok." };
  if (failure.status === 404) return { ok: false, message: "Kayıt bulunamadı. Silinmiş olabilir." };
  if (failure.status === 422) {
    return { ok: false, message: failure.message, fieldErrors: failure.fieldErrors };
  }
  return { ok: false, message: failure.message };
}
