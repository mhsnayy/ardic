"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { z } from "zod";
import type { ActionState } from "@/lib/action-result";
import { loginTo, revokeToken, type LoginOutcome } from "@/lib/api/auth";
import { cookieOptions, EP_COOKIE, PS_COOKIE, SESSION_MAX_AGE } from "@/lib/session";
import { formToObject, validate } from "@/lib/validate";

const schema = z.object({
  email: z.string().trim().min(1, "E-posta zorunludur.").email("Geçerli bir e-posta girin."),
  password: z.string().min(1, "Şifre zorunludur."),
});

function maxAgeFor(expiresAt: string): number {
  const ms = Date.parse(expiresAt) - Date.now();
  if (Number.isNaN(ms)) return SESSION_MAX_AGE;
  return Math.max(60, Math.min(SESSION_MAX_AGE, Math.floor(ms / 1000)));
}

export async function loginAction(_prev: ActionState, formData: FormData): Promise<ActionState> {
  const result = await doLogin(formData);
  if (result && !result.ok) {
    return { ...result, values: { email: String(formData.get("email") ?? "") } };
  }
  return result;
}

async function doLogin(formData: FormData): Promise<ActionState> {
  const v = validate(schema, formToObject(formData));
  if (!v.ok) return v.result;
  const { email, password } = v.data;

  const [ps, ep]: [LoginOutcome, LoginOutcome] = await Promise.all([
    loginTo("personnel", email, password),
    loginTo("education", email, password),
  ]);

  if (ps.kind === "ok" && ep.kind === "ok") {
    const jar = await cookies();
    jar.set(PS_COOKIE, ps.data.token, cookieOptions(maxAgeFor(ps.data.expires_at)));
    jar.set(EP_COOKIE, ep.data.token, cookieOptions(maxAgeFor(ep.data.expires_at)));
  } else {
    // Biri başarısızsa diğerinin token'ını iptal et
    await Promise.all([
      ps.kind === "ok" ? revokeToken("personnel", ps.data.token) : Promise.resolve(),
      ep.kind === "ok" ? revokeToken("education", ep.data.token) : Promise.resolve(),
    ]);

    const outcomes = [ps, ep];
    if (outcomes.some((o) => o.kind === "network")) {
      return { ok: false, message: "Sunucuya ulaşılamıyor." };
    }
    if (outcomes.some((o) => o.kind === "http" && o.status === 429)) {
      return { ok: false, message: "Çok fazla deneme, lütfen bir dakika sonra tekrar deneyin." };
    }
    if (outcomes.some((o) => o.kind === "http" && (o.status === 422 || o.status === 401))) {
      const msg = "E-posta veya şifre hatalı.";
      return { ok: false, message: msg, fieldErrors: { email: [msg] } };
    }
    return { ok: false, message: "Giriş yapılamadı. Lütfen daha sonra tekrar deneyin." };
  }

  redirect("/");
}
