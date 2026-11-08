import "server-only";
import { baseUrl, type Service } from "./client";
import type { AuthLoginResponse } from "./types";

export type LoginOutcome =
  | { kind: "ok"; data: AuthLoginResponse }
  | { kind: "http"; status: number }
  | { kind: "network" };

export async function loginTo(service: Service, email: string, password: string): Promise<LoginOutcome> {
  try {
    const res = await fetch(`${baseUrl(service)}/api/auth/login`, {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify({ email, password }),
      cache: "no-store",
      signal: AbortSignal.timeout(10000),
    });
    if (!res.ok) return { kind: "http", status: res.status };
    return { kind: "ok", data: (await res.json()) as AuthLoginResponse };
  } catch {
    return { kind: "network" };
  }
}

/** Token iptali, en iyi çaba (hata fırlatmaz). */
export async function revokeToken(service: Service, token: string | undefined): Promise<void> {
  if (!token) return;
  try {
    await fetch(`${baseUrl(service)}/api/auth/logout`, {
      method: "POST",
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      cache: "no-store",
      signal: AbortSignal.timeout(5000),
    });
  } catch {
    // yoksay
  }
}
