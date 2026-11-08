import { NextResponse, type NextRequest } from "next/server";
import { revokeToken } from "@/lib/api/auth";
import { EP_COOKIE, PS_COOKIE } from "@/lib/session";

export const dynamic = "force-dynamic";

export async function GET(request: NextRequest) {
  const ps = request.cookies.get(PS_COOKIE)?.value;
  const ep = request.cookies.get(EP_COOKIE)?.value;

  // Token iptali, en iyi çaba
  await Promise.all([revokeToken("personnel", ps), revokeToken("education", ep)]);

  const expired = request.nextUrl.searchParams.get("expired") === "1";
  const path = expired ? "/login?expired=1" : "/login";
  const base = process.env.PUBLIC_FRONTEND_URL?.replace(/\/+$/, "");

  const res = new NextResponse(null, {
    status: 307,
    headers: { Location: base ? `${base}${path}` : path },
  });
  res.cookies.delete(PS_COOKIE);
  res.cookies.delete(EP_COOKIE);
  return res;
}
