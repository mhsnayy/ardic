import { NextResponse, type NextRequest } from "next/server";
import { EP_COOKIE, PS_COOKIE } from "@/lib/session";

// Sadece cookie varlığına bakar; token doğrulaması backend'dedir.
export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  if (pathname === "/logout" || pathname === "/ogren" || pathname.startsWith("/ogren/")) {
    return NextResponse.next();
  }

  const loggedIn = Boolean(request.cookies.get(PS_COOKIE)?.value && request.cookies.get(EP_COOKIE)?.value);

  if (pathname === "/login") {
    if (loggedIn) return redirectTo(request, "/");
    return NextResponse.next();
  }

  if (!loggedIn) return redirectTo(request, "/login");
  return NextResponse.next();
}

function redirectTo(request: NextRequest, path: string) {
  const base = process.env.PUBLIC_FRONTEND_URL || request.url;
  return NextResponse.redirect(new URL(path, base));
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico|.*\\.[a-zA-Z0-9]+$).*)"],
};
