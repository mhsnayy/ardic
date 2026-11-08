import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { LoginForm } from "./login-form";

export const dynamic = "force-dynamic";

export default async function LoginPage({ searchParams }: PageProps<"/login">) {
  const sp = await searchParams;
  const expired = sp.expired === "1";

  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/40 p-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle className="text-xl">Eğitim Yönetim Paneli</CardTitle>
          <CardDescription>Devam etmek için yönetici hesabınızla giriş yapın.</CardDescription>
        </CardHeader>
        <CardContent>
          <LoginForm expired={expired} />
        </CardContent>
      </Card>
    </main>
  );
}
