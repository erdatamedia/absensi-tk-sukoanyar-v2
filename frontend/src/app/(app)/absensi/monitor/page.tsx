"use client";

import { useCallback, useEffect, useState } from "react";
import { apiFetch } from "@/lib/api";
import type { MonitorAktivitas, MonitorSummary } from "@/lib/types";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { PageHeader } from "@/components/page-header";
import { GlassHero, GlassTile } from "@/components/glass-hero";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

interface MonitorResponse {
  status: string;
  tanggal: string;
  summary: MonitorSummary;
  aktivitas: MonitorAktivitas[];
}

const STAT_LABELS: { key: keyof MonitorSummary; label: string }[] = [
  { key: "masuk", label: "Sudah Masuk" },
  { key: "pulang", label: "Sudah Pulang" },
  { key: "belum_pulang", label: "Belum Pulang" },
  { key: "alpha", label: "Alpha" },
];

export default function MonitorPage() {
  const [data, setData] = useState<MonitorResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const today = new Date().toISOString().slice(0, 10);
      const result = await apiFetch<MonitorResponse>(
        `/api/absensi/monitor/data?tanggal=${today}&limit=25`
      );
      setData(result);
      setError(null);
    } catch {
      setError("Gagal memuat data monitor.");
    }
  }, []);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- initial load + polling
    load();
    const interval = setInterval(load, 15000);
    return () => clearInterval(interval);
  }, [load]);

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Operasional"
        title="Monitor Absensi Hari Ini"
        description="Data diperbarui otomatis setiap 15 detik."
      />

      {error && (
        <p className="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </p>
      )}

      <GlassHero>
        <div className="grid gap-4 sm:grid-cols-4">
          {STAT_LABELS.map(({ key, label }) => (
            <GlassTile
              key={key}
              label={label}
              value={data?.summary[key] ?? "-"}
              tone={key === "masuk" || key === "pulang" ? "positive" : "default"}
            />
          ))}
        </div>
      </GlassHero>

      <Card>
        <CardHeader>
          <CardTitle className="text-sm">Aktivitas Terbaru</CardTitle>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nama</TableHead>
                <TableHead>Kelas</TableHead>
                <TableHead>Masuk</TableHead>
                <TableHead>Pulang</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Sumber</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data?.aktivitas.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className="text-center text-sm text-muted-foreground">
                    Belum ada aktivitas hari ini.
                  </TableCell>
                </TableRow>
              )}
              {data?.aktivitas.map((item) => (
                <TableRow key={item.id}>
                  <TableCell className="font-medium">{item.nama}</TableCell>
                  <TableCell>{item.kelas}</TableCell>
                  <TableCell>{item.jam_masuk}</TableCell>
                  <TableCell>{item.jam_pulang}</TableCell>
                  <TableCell>
                    <div className="flex items-center gap-1.5">
                      <Badge variant="secondary" className="capitalize">
                        {item.status_absensi}
                      </Badge>
                      {Boolean(item.terlambat) && (
                        <Badge variant="destructive">Terlambat</Badge>
                      )}
                    </div>
                  </TableCell>
                  <TableCell className="text-xs uppercase text-muted-foreground">
                    {item.sumber}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
