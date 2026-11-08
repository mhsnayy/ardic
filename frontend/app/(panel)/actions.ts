"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { revokeToken } from "@/lib/api/auth";
import { EP_COOKIE, PS_COOKIE } from "@/lib/session";

/** AUTH-3: Çıkışta iki token da iptal edilir, cookie'ler silinir. */
export async function logoutAction() {
  const jar = await cookies();
  await Promise.all([
    revokeToken("personnel", jar.get(PS_COOKIE)?.value),
    revokeToken("education", jar.get(EP_COOKIE)?.value),
  ]);
  jar.delete(PS_COOKIE);
  jar.delete(EP_COOKIE);
  redirect("/login");
}
