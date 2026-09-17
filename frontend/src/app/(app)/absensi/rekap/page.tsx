"use client";

import { useCallback, useEffect, useState } from "react";
import { apiFetch, apiFileUrl } from "@/lib/api";
import type { Kelas } from "@/lib/types";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { PageHeader } from "@/components/page-header";
import { GlassHero, GlassTile } from "@/components/glass-hero";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

interface ClassReportRow {
  nis: string;
  nama: string;
  status_label: string;
  total_hadir: number;
  total_izin: number;
  total_sakit: number;
  total_alpha: number;
  terlambat: boolean;
}

interface ClassReport {
  kelas: Kelas;
  summary: Record<string, number>;
  rows: ClassReportRow[];
}

interface TrendPoint {
  date: string;
  label: string;
  hadir: number;
  izin: number;
  alpha: number;
  total: number;
}

interface RekapResponse {
  status: string;
  period_meta: {
    period: string;
    label: string;
    anchor_date: string;
    range_label: string;
  };
  kelas_list: Kelas[];
  class_reports: ClassReport[];
  summary: Record<string, number>;
  trend: { points: TrendPoint[]; max: number; average: number; peak: number };
}

const ALL = "__all__";

const PERIOD_LABELS: Record<string, string> = {
  daily: "Harian",
  weekly: "Mingguan",
  monthly: "Bulanan",
};

const SUMMARY_LABELS: { key: string; label: string }[] = [
  { key: "total_siswa", label: "Total Siswa" },
  { key: "hadir", label: "Hadir" },
  { key: "izin", label: "Izin" },
  { key: "sakit", label: "Sakit" },
  { key: "alpha", label: "Alpha" },
  { key: "terlambat", label: "Terlambat" },
];

export default function RekapPage() {
  const [period, setPeriod] = useState("daily");
  const [tanggal, setTanggal] = useState(() => new Date().toISOString().slice(0, 10));
  const [kelasId, setKelasId] = useState(ALL);
  const [data, setData] = useState<RekapResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  const buildParams = useCallback(() => {
    const params = new URLSearchParams({ period, tanggal });
    if (kelasId !== ALL) params.set("kelas_id", kelasId);
    return params;
  }, [period, tanggal, kelasId]);

  const load = useCallback(async () => {
    try {
      const result = await apiFetch<RekapResponse>(`/api/absensi/rekap?${buildParams()}`);
      setData(result);
      setError(null);
    } catch {
      setError("Gagal memuat rekap.");
    }
  }, [buildParams]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- refetch when filters change
    load();
  }, [load]);

  const exportCsvUrl = apiFileUrl(`/absensi/rekap/export?${buildParams()}&format=csv`);
  const exportPdfUrl = apiFileUrl(`/absensi/rekap/export?${buildParams()}&format=pdf`);

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Operasional"
        title="Rekap Absensi"
        description={data?.period_meta.range_label}
        actions={
          <>
            <a href={exportCsvUrl} target="_blank" rel="noreferrer">
              <Button variant="outline" size="sm">Export CSV</Button>
            </a>
            <a href={exportPdfUrl} target="_blank" rel="noreferrer">
              <Button variant="outline" size="sm">Export PDF</Button>
            </a>
          </>
        }
      />

      <Card>
        <CardContent className="grid grid-cols-2 gap-3 pt-6 sm:grid-cols-3">
          <div>
            <Label>Periode</Label>
            <Select value={period} onValueChange={(v) => setPeriod(v ?? "daily")}>
              <SelectTrigger className="w-full"><SelectValue>{(v: string) => PERIOD_LABELS[v] ?? v}</SelectValue></SelectTrigger>
              <SelectContent>
                <SelectItem value="daily">Harian</SelectItem>
                <SelectItem value="weekly">Mingguan</SelectItem>
                <SelectItem value="monthly">Bulanan</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label>Tanggal Acuan</Label>
            <input
              type="date"
              value={tanggal}
              onChange={(e) => setTanggal(e.target.value)}
              className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs"
            />
          </div>
          <div>
            <Label>Kelas</Label>
            <Select value={kelasId} onValueChange={(v) => setKelasId(v ?? ALL)}>
              <SelectTrigger className="w-full">
                <SelectValue>
                  {(v: string) =>
                    v === ALL ? "Semua Kelas" : data?.kelas_list.find((k) => String(k.id) === v)?.nama_kelas ?? v
                  }
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Semua Kelas</SelectItem>
                {data?.kelas_list.map((k) => (
                  <SelectItem key={k.id} value={String(k.id)}>{k.nama_kelas}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {error && (
        <p className="rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>
      )}

      <GlassHero>
        <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
          {SUMMARY_LABELS.map(({ key, label }) => (
            <GlassTile
              key={key}
              label={label}
              value={data?.summary[key] ?? "-"}
              tone={key === "hadir" ? "positive" : "default"}
            />
          ))}
        </div>
      </GlassHero>

      {data && data.trend.points.length > 1 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-sm">Tren Kehadiran</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex h-40 items-end gap-1.5">
              {data.trend.points.map((point) => (
                <div key={point.date} className="flex flex-1 flex-col items-center gap-1">
                  <div
                    className="w-full rounded-t bg-neutral-800"
                    style={{
                      height: `${Math.max(4, (point.hadir / data.trend.max) * 100)}%`,
                    }}
                    title={`${point.label}: ${point.hadir} hadir`}
                  />
                  <span className="text-[10px] text-muted-foreground">{point.label}</span>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {data?.class_reports.map((report) => (
        <Card key={report.kelas.id}>
          <CardHeader>
            <CardTitle className="text-sm">{report.kelas.nama_kelas}</CardTitle>
          </CardHeader>
          <CardContent className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>NIS</TableHead>
                  <TableHead>Nama</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {report.rows.map((row) => (
                  <TableRow key={row.nis}>
                    <TableCell>{row.nis}</TableCell>
                    <TableCell className="font-medium">{row.nama}</TableCell>
                    <TableCell>{row.status_label}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
