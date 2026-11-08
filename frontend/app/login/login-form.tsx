"use client";

import { useActionState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { loginAction } from "./actions";

export function LoginForm({ expired }: { expired: boolean }) {
  const [state, formAction, pending] = useActionState(loginAction, null);
  const emailError = state && !state.ok ? state.fieldErrors?.email?.[0] : undefined;
  const passwordError = state && !state.ok ? state.fieldErrors?.password?.[0] : undefined;

  const email = state && !state.ok ? (state.values?.email ?? "") : "";

  return (
    <form action={formAction} className="space-y-4">
      {expired && !state && (
        <Alert>
          <AlertDescription>Oturumunuzun süresi doldu. Lütfen tekrar giriş yapın.</AlertDescription>
        </Alert>
      )}
      {state && !state.ok && !emailError && !passwordError && (
        <Alert variant="destructive">
          <AlertDescription>{state.message}</AlertDescription>
        </Alert>
      )}
      <div className="space-y-1.5">
        <Label htmlFor="email">E-posta</Label>
        <Input
          key={email}
          id="email"
          name="email"
          type="email"
          autoComplete="username"
          defaultValue={email}
          required
          aria-invalid={!!emailError}
        />
        {emailError && <p className="text-sm text-destructive">{emailError}</p>}
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="password">Şifre</Label>
        <Input
          id="password"
          name="password"
          type="password"
          autoComplete="current-password"
          required
          aria-invalid={!!passwordError}
        />
        {passwordError && <p className="text-sm text-destructive">{passwordError}</p>}
      </div>
      <Button type="submit" className="w-full" disabled={pending}>
        {pending ? "Giriş yapılıyor..." : "Giriş Yap"}
      </Button>
    </form>
  );
}
