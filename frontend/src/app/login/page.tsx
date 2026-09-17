"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/context/auth-context";
import { ApiError } from "@/lib/api";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { GlassTile } from "@/components/glass-hero";

const STEPS = [
  { label: "Langkah 1", text: "Kenali wajah siswa" },
  { label: "Langkah 2", text: "Simpan bukti foto" },
  { label: "Langkah 3", text: "Rekap otomatis tersusun" },
];

export default function LoginPage() {
  const { user, loading, login } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!loading && user) {
      router.replace("/absensi/monitor");
    }
  }, [loading, user, router]);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await login(email, password);
      router.replace("/absensi/monitor");
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        setError("Email atau kata sandi salah.");
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
          <section className="glass-hero hidden rounded-[32px] px-8 py-10 text-white shadow-xl shadow-indigo-500/20 sm:px-10 lg:block">
            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-white/80">
              Sistem Absensi TK
            </p>
            <h1 className="mt-4 max-w-xl text-4xl font-semibold leading-tight">
              Scan wajah, ambil bukti hadir, dan simpan rekap otomatis dari satu titik absensi.
            </h1>
            <p className="mt-4 max-w-2xl text-sm leading-7 text-white/85">
              Aplikasi ini dipakai guru di laptop sekolah untuk absensi masuk dan pulang. Fokus
              sistem hanya pada operasional absensi, bukti foto, dan rekap otomatis.
            </p>
            <div className="mt-8 grid gap-4 sm:grid-cols-3">
              {STEPS.map((step) => (
                <GlassTile key={step.label} label={step.label} value={step.text} />
              ))}
            </div>
          </section>

          <section className="rounded-[32px] border border-white/60 bg-white/70 p-6 shadow-lg shadow-slate-900/5 backdrop-blur-xl sm:p-8">
            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted-foreground">
              Akses Admin
            </p>
            <h2 className="mt-2 text-2xl font-semibold text-foreground">Masuk ke sistem absensi</h2>
            <p className="mt-2 text-sm leading-6 text-muted-foreground">
              Gunakan akun admin sekolah. Tidak ada registrasi publik pada aplikasi ini.
            </p>

            <form onSubmit={handleSubmit} className="mt-6 space-y-5">
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
