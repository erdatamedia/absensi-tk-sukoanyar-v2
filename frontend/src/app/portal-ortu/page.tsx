"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/context/auth-context";
import { apiFetch } from "@/lib/api";
import type { Absensi, Paginated } from "@/lib/types";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

interface AbsensiAnakResponse {
  status: string;
  absensi: Paginated<Absensi>;
}

const STATUS_LABELS: Record<string, string> = {
  hadir: "Hadir",
  izin: "Izin",
  sakit: "Sakit",
  alpha: "Alpha",
};

export default function PortalOrtuPage() {
  const { user, loading, logout } = useAuth();
  const router = useRouter();
  const [page, setPage] = useState(1);
  const [data, setData] = useState<AbsensiAnakResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.replace("/login");
    } else if (user.role !== "orang_tua") {
      router.replace("/absensi/monitor");
    }
  }, [loading, user, router]);

  const load = useCallback(async () => {
    try {
      const result = await apiFetch<AbsensiAnakResponse>(
        `/api/portal-ortu/absensi-anak?page=${page}`
      );
      setData(result);
      setError(null);
    } catch {
      setError("Gagal memuat riwayat kehadiran.");
    }
  }, [page]);

  useEffect(() => {
    if (user?.role !== "orang_tua") return;
    // eslint-disable-next-line react-hooks/set-state-in-effect -- refetch when page changes
    load();
  }, [load, user]);

  if (loading || !user || user.role !== "orang_tua") {
    return (
      <div className="app-gradient-bg flex min-h-screen items-center justify-center text-sm text-muted-foreground">
        Memuat...
      </div>
    );
  }

  return (
    <div className="app-gradient-bg min-h-screen">
      <header className="mx-auto flex w-full max-w-2xl items-center justify-between px-4 pt-6">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted-foreground">
            Portal Orang Tua
          </p>
          <h1 className="mt-1 text-xl font-semibold text-foreground">Riwayat Kehadiran Anak</h1>
        </div>
        <Button variant="outline" size="sm" onClick={() => logout()}>
          Keluar
        </Button>
      </header>

      <main className="mx-auto w-full max-w-2xl space-y-4 px-4 py-6">
        {error && (
          <p className="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>
        )}

        {data?.absensi.data.length === 0 && (
          <Card>
            <CardContent className="pt-6 text-center text-sm text-muted-foreground">
              Belum ada data kehadiran.
            </CardContent>
          </Card>
        )}

        {data?.absensi.data.map((absensi) => (
          <Card key={absensi.id}>
            <CardContent className="pt-6">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-semibold text-foreground">{absensi.siswa?.nama}</p>
                  <p className="text-xs text-muted-foreground">
                    {absensi.siswa?.kelas?.nama_kelas} &middot;{" "}
                    {new Date(absensi.tanggal).toLocaleDateString("id-ID", {
                      weekday: "long",
                      day: "numeric",
                      month: "long",
                      year: "numeric",
                    })}
                  </p>
                </div>
                <div className="flex items-center gap-1.5">
                  <Badge variant="secondary">{STATUS_LABELS[absensi.status] ?? absensi.status}</Badge>
                  {Boolean(absensi.terlambat) && <Badge variant="destructive">Terlambat</Badge>}
                </div>
              </div>
              <div className="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div>
                  <p className="text-xs text-muted-foreground">Jam Masuk</p>
                  <p className="font-medium text-foreground">{absensi.jam_masuk ?? "-"}</p>
                </div>
                <div>
                  <p className="text-xs text-muted-foreground">Jam Pulang</p>
                  <p className="font-medium text-foreground">{absensi.jam_pulang ?? "-"}</p>
                </div>
              </div>
            </CardContent>
          </Card>
        ))}

        {data && data.absensi.last_page > 1 && (
          <div className="flex items-center justify-between pt-2 text-sm text-muted-foreground">
            <span>
              Halaman {data.absensi.current_page} dari {data.absensi.last_page}
            </span>
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
              >
                Sebelumnya
              </Button>
              <Button
                variant="outline"
                size="sm"
                disabled={page >= data.absensi.last_page}
                onClick={() => setPage((p) => p + 1)}
              >
                Berikutnya
              </Button>
            </div>
          </div>
        )}
      </main>
    </div>
  );
}
