// Cookie adları ve ortak seçenekler. Edge (middleware) tarafından da içe aktarılır, bu yüzden server-only değildir.
export const PS_COOKIE = "ps_session";
export const EP_COOKIE = "ep_session";
export const SESSION_MAX_AGE = 60 * 60 * 8; // 8 saat

export function cookieOptions(maxAge: number = SESSION_MAX_AGE) {
  return {
    httpOnly: true,
    sameSite: "lax" as const,
    path: "/",
    secure: process.env.COOKIE_SECURE === "true",
    maxAge,
  };
}
