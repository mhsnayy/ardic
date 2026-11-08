import "server-only";
import { z } from "zod";
import { baseUrl } from "./client";
import type { Learning, ProgressResult } from "./types";

// Öğrenme token'ı link içindedir; panel cookie'leri bu dosyada kullanılmaz.
export const tokenSchema = z.string().regex(/^[A-Za-z0-9]{20,128}$/);

export type LearnOutcome<T> =
  | { status: "ok"; data: T }
  | { status: "invalid" }
  | { status: "error"; message: string; fieldErrors?: Record<string, string[]> };

async function learnFetch<T>(token: string, path: string, method: "GET" | "POST", body?: unknown): Promise<LearnOutcome<T>> {
  if (!tokenSchema.safeParse(token).success) return { status: "invalid" };
  let res: Response;
  try {
    res = await fetch(baseUrl("education") + path, {
      method,
      headers: {
        Accept: "application/json",
        "X-Learning-Token": token,
        ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
      cache: "no-store",
      signal: AbortSignal.timeout(15000),
    });
  } catch {
    return { status: "error", message: "Sunucuya ulaşılamıyor." };
  }
  if (res.status === 404) return { status: "invalid" };
  let json: unknown = null;
  try {
    json = await res.json();
  } catch {
    json = null;
  }
  if (!res.ok) {
    const b = (json ?? {}) as { message?: string; errors?: Record<string, string[]> };
    return { status: "error", message: b.message || `İstek başarısız oldu (${res.status}).`, fieldErrors: b.errors };
  }
  return { status: "ok", data: (json as { data: T }).data };
}

export function getLearning(token: string) {
  return learnFetch<Learning>(token, "/api/learn", "GET");
}

export function reportProgress(token: string, contentId: number, body: { position?: number; duration?: number }) {
  return learnFetch<ProgressResult>(token, `/api/learn/contents/${encodeURIComponent(String(contentId))}/progress`, "POST", body);
}
