"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/context/auth-context";
import { ApiError } from "@/lib/api";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { GlassTile } from "@/components/glass-hero";

const STEPS = [
  { label: "Langkah 1", text: "Kenali wajah siswa" },
  { label: "Langkah 2", text: "Simpan bukti foto" },
  { label: "Langkah 3", text: "Rekap otomatis tersusun" },
];

type LoginMode = "staff" | "ortu";

export default function LoginPage() {
  const { user, loading, login, loginParent } = useAuth();
  const router = useRouter();
  const [mode, setMode] = useState<LoginMode>("staff");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [phone, setPhone] = useState("");
  const [pin, setPin] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (loading || !user) return;
    router.replace(user.role === "orang_tua" ? "/portal-ortu" : "/absensi/monitor");
  }, [loading, user, router]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      if (mode === "staff") {
        await login(email, password);
      } else {
        await loginParent(phone, pin);
      }
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        setError(mode === "staff" ? "Email atau kata sandi salah." : "Nomor HP atau PIN salah.");
      } else {
        setError("Gagal masuk. Silakan coba lagi.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="relative min-h-screen overflow-hidden bg-background">
      <div className="auth-gradient-bg pointer-events-none absolute inset-0" />
      <div className="relative mx-auto flex min-h-screen max-w-6xl items-center px-4 py-8 sm:px-6 lg:px-8">
        <div className="grid w-full gap-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(360px,440px)] lg:items-center">
          <section className="glass-hero hidden rounded-[32px] px-8 py-10 text-white shadow-xl shadow-orange-500/20 sm:px-10 lg:block">
            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-white/80">
              Sistem Absensi TK
            </p>
            <h1 className="mt-4 max-w-xl text-4xl font-semibold leading-tight">
              Scan wajah, ambil bukti hadir, dan simpan rekap otomatis dari satu titik absensi.
            </h1>
            <p className="mt-4 max-w-2xl text-sm leading-7 text-white/85">
              Aplikasi ini dipakai guru di laptop sekolah untuk absensi masuk dan pulang. Orang tua
              bisa memantau kehadiran anak lewat portal terpisah.
            </p>
            <div className="mt-8 grid gap-4 sm:grid-cols-3">
              {STEPS.map((step) => (
                <GlassTile key={step.label} label={step.label} value={step.text} />
              ))}
            </div>
          </section>

          <section className="rounded-[32px] border border-white/60 bg-white/70 p-6 shadow-lg shadow-slate-900/5 backdrop-blur-xl sm:p-8">
            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted-foreground">
              Akses Sistem
            </p>
            <h2 className="mt-2 text-2xl font-semibold text-foreground">Masuk ke sistem absensi</h2>

            <Tabs
              value={mode}
              onValueChange={(v) => {
                setMode((v ?? "staff") as LoginMode);
                setError(null);
              }}
              className="mt-5"
            >
              <TabsList className="w-full">
                <TabsTrigger value="staff" className="flex-1">Admin &amp; Guru</TabsTrigger>
                <TabsTrigger value="ortu" className="flex-1">Orang Tua</TabsTrigger>
              </TabsList>
            </Tabs>

            {mode === "staff" ? (
              <p className="mt-3 text-sm leading-6 text-muted-foreground">
                Gunakan akun admin atau guru sekolah.
              </p>
            ) : (
              <p className="mt-3 text-sm leading-6 text-muted-foreground">
                Masukkan nomor HP dan PIN yang diberikan sekolah.
              </p>
            )}

            <form onSubmit={handleSubmit} className="mt-5 space-y-5">
              {mode === "staff" ? (
                <>
                  <div>
                    <Label htmlFor="email">Email</Label>
                    <Input
                      id="email"
                      type="email"
                      required
                      autoComplete="username"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      className="mt-2"
                    />
                  </div>

                  <div>
                    <Label htmlFor="password">Kata Sandi</Label>
                    <Input
                      id="password"
                      type="password"
                      required
                      autoComplete="current-password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      className="mt-2"
                    />
                  </div>
                </>
              ) : (
                <>
                  <div>
                    <Label htmlFor="phone">Nomor HP</Label>
                    <Input
                      id="phone"
                      type="tel"
                      required
                      autoComplete="username"
                      placeholder="08xxxxxxxxxx"
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      className="mt-2"
                    />
                  </div>

                  <div>
                    <Label htmlFor="pin">PIN</Label>
                    <Input
                      id="pin"
                      type="password"
                      inputMode="numeric"
                      required
                      autoComplete="current-password"
                      value={pin}
                      onChange={(e) => setPin(e.target.value)}
                      className="mt-2"
                    />
                  </div>
                </>
              )}

              {error && (
                <p className="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>
              )}

              <Button type="submit" disabled={submitting} className="w-full">
                {submitting ? "Memproses..." : "Masuk"}
              </Button>
            </form>
          </section>
        </div>
      </div>
    </div>
  );
}
