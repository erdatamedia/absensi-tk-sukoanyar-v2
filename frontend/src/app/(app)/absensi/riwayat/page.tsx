"use client";

import { useCallback, useEffect, useState } from "react";
import { apiFetch, apiFileUrl, ApiError } from "@/lib/api";
import type { Absensi, Kelas, Paginated, Siswa } from "@/lib/types";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
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
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { PageHeader } from "@/components/page-header";

interface RiwayatResponse {
  status: string;
  riwayat: Paginated<Absensi>;
  summary: { total: number; sudah_pulang: number; belum_pulang: number };
  kelas_list: Kelas[];
  siswa_belum_masuk: Siswa[];
  siswa_belum_pulang: Siswa[];
}

const ALL = "__all__";

const STATUS_LABELS: Record<string, string> = {
  [ALL]: "Semua",
  hadir: "Hadir",
  izin: "Izin",
  sakit: "Sakit",
  alpha: "Alpha",
};

const SUMBER_LABELS: Record<string, string> = {
  [ALL]: "Semua",
  scan_qr: "Scan QR",
  manual: "Manual",
  auto_alpha: "Auto Alpha",
};

const TERLAMBAT_LABELS: Record<string, string> = {
  [ALL]: "Semua",
  ya: "Ya",
  tidak: "Tidak",
};

export default function RiwayatPage() {
  const [tanggal, setTanggal] = useState(() => new Date().toISOString().slice(0, 10));
  const [kelasId, setKelasId] = useState(ALL);
  const [q, setQ] = useState("");
  const [statusFilter, setStatusFilter] = useState(ALL);
  const [sumberFilter, setSumberFilter] = useState(ALL);
  const [terlambatFilter, setTerlambatFilter] = useState(ALL);
  const [page, setPage] = useState(1);
  const [data, setData] = useState<RiwayatResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const buildParams = useCallback(
    (extra: Record<string, string> = {}) => {
      const params = new URLSearchParams({ tanggal, page: String(page), ...extra });
      if (kelasId !== ALL) params.set("kelas_id", kelasId);
      if (q) params.set("q", q);
      if (statusFilter !== ALL) params.set("status_filter", statusFilter);
      if (sumberFilter !== ALL) params.set("sumber_filter", sumberFilter);
      if (terlambatFilter !== ALL) params.set("terlambat_filter", terlambatFilter);
      return params;
    },
    [tanggal, kelasId, q, statusFilter, sumberFilter, terlambatFilter, page]
  );

  const load = useCallback(async () => {
    try {
      const params = buildParams();
      const result = await apiFetch<RiwayatResponse>(`/api/absensi/riwayat?${params}`);
      setData(result);
      setError(null);
    } catch {
      setError("Gagal memuat riwayat absensi.");
    }
  }, [buildParams]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- refetch when filters change
    load();
  }, [load]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- reset pagination when filters change
    setPage(1);
  }, [tanggal, kelasId, q, statusFilter, sumberFilter, terlambatFilter]);

  async function updateStatus(absensi: Absensi, status: string) {
    setBusy(true);
    try {
      await apiFetch(`/api/absensi/${absensi.id}/status`, {
        method: "POST",
        body: JSON.stringify({ status }),
      });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal memperbarui status.");
    } finally {
      setBusy(false);
    }
  }

  async function updateWaktu(absensi: Absensi, jamMasuk: string, jamPulang: string) {
    setBusy(true);
    try {
      await apiFetch(`/api/absensi/${absensi.id}/waktu`, {
        method: "POST",
        body: JSON.stringify({
          jam_masuk: jamMasuk || undefined,
          jam_pulang: jamPulang || undefined,
        }),
      });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal memperbarui jam.");
    } finally {
      setBusy(false);
    }
  }

  async function destroy(absensi: Absensi) {
    if (!confirm(`Hapus data absensi ${absensi.siswa?.nama ?? ""}?`)) return;
    setBusy(true);
    try {
      await apiFetch(`/api/absensi/${absensi.id}`, { method: "DELETE" });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal menghapus data.");
    } finally {
      setBusy(false);
    }
  }

  async function markAlpha() {
    setBusy(true);
    try {
      const params = buildParams();
      params.delete("page");
      await apiFetch(`/api/absensi/mark-alpha`, {
        method: "POST",
        body: JSON.stringify(Object.fromEntries(params)),
      });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal menandai alpha.");
    } finally {
      setBusy(false);
    }
  }

  async function unmarkAlpha() {
    setBusy(true);
    try {
      const params = buildParams();
      params.delete("page");
      await apiFetch(`/api/absensi/unmark-alpha`, {
        method: "POST",
        body: JSON.stringify(Object.fromEntries(params)),
      });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal membatalkan alpha.");
    } finally {
      setBusy(false);
    }
  }

  const exportUrl = apiFileUrl(`/absensi/riwayat/export?${buildParams()}`);

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Operasional"
        title="Riwayat Absensi"
        actions={
          <>
            <Button variant="outline" size="sm" onClick={markAlpha} disabled={busy}>
              Tandai Alpha
            </Button>
            <Button variant="outline" size="sm" onClick={unmarkAlpha} disabled={busy}>
              Batalkan Alpha
            </Button>
            <a href={exportUrl} target="_blank" rel="noreferrer">
              <Button variant="outline" size="sm">Export CSV</Button>
            </a>
          </>
        }
      />

      <Card>
        <CardContent className="grid grid-cols-2 gap-3 pt-6 sm:grid-cols-3 lg:grid-cols-6">
          <div>
            <Label>Tanggal</Label>
            <Input type="date" value={tanggal} onChange={(e) => setTanggal(e.target.value)} />
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
          <div>
            <Label>Cari Nama/NIS</Label>
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari..." />
          </div>
          <div>
            <Label>Status</Label>
            <Select value={statusFilter} onValueChange={(v) => setStatusFilter(v ?? ALL)}>
              <SelectTrigger className="w-full"><SelectValue>{(v: string) => STATUS_LABELS[v] ?? v}</SelectValue></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Semua</SelectItem>
                <SelectItem value="hadir">Hadir</SelectItem>
                <SelectItem value="izin">Izin</SelectItem>
                <SelectItem value="sakit">Sakit</SelectItem>
                <SelectItem value="alpha">Alpha</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label>Sumber</Label>
            <Select value={sumberFilter} onValueChange={(v) => setSumberFilter(v ?? ALL)}>
              <SelectTrigger className="w-full"><SelectValue>{(v: string) => SUMBER_LABELS[v] ?? v}</SelectValue></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Semua</SelectItem>
                <SelectItem value="scan_qr">Scan QR</SelectItem>
                <SelectItem value="manual">Manual</SelectItem>
                <SelectItem value="auto_alpha">Auto Alpha</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label>Terlambat</Label>
            <Select value={terlambatFilter} onValueChange={(v) => setTerlambatFilter(v ?? ALL)}>
              <SelectTrigger className="w-full"><SelectValue>{(v: string) => TERLAMBAT_LABELS[v] ?? v}</SelectValue></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Semua</SelectItem>
                <SelectItem value="ya">Ya</SelectItem>
                <SelectItem value="tidak">Tidak</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {error && (
        <p className="rounded-2xl bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>
      )}

      <div className="grid grid-cols-3 gap-3">
        <Card><CardHeader className="pb-2"><CardTitle className="text-xs text-muted-foreground">Total</CardTitle></CardHeader><CardContent><p className="text-xl font-semibold">{data?.summary.total ?? "-"}</p></CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-xs text-muted-foreground">Sudah Pulang</CardTitle></CardHeader><CardContent><p className="text-xl font-semibold">{data?.summary.sudah_pulang ?? "-"}</p></CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-xs text-muted-foreground">Belum Pulang</CardTitle></CardHeader><CardContent><p className="text-xl font-semibold">{data?.summary.belum_pulang ?? "-"}</p></CardContent></Card>
      </div>

      <Tabs defaultValue="riwayat">
        <TabsList>
          <TabsTrigger value="riwayat">Riwayat</TabsTrigger>
          <TabsTrigger value="belum-masuk">Belum Masuk ({data?.siswa_belum_masuk.length ?? 0})</TabsTrigger>
          <TabsTrigger value="belum-pulang">Belum Pulang ({data?.siswa_belum_pulang.length ?? 0})</TabsTrigger>
        </TabsList>

        <TabsContent value="riwayat">
          <Card>
            <CardContent className="overflow-x-auto pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Nama</TableHead>
                    <TableHead>Kelas</TableHead>
                    <TableHead>Masuk</TableHead>
                    <TableHead>Pulang</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Sumber</TableHead>
                    <TableHead className="text-right">Aksi</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data?.riwayat.data.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center text-sm text-muted-foreground">
                        Tidak ada data.
                      </TableCell>
                    </TableRow>
                  )}
                  {data?.riwayat.data.map((absensi) => (
                    <RiwayatRow
                      key={absensi.id}
                      absensi={absensi}
                      busy={busy}
                      onUpdateStatus={updateStatus}
                      onUpdateWaktu={updateWaktu}
                      onDestroy={destroy}
                    />
                  ))}
                </TableBody>
              </Table>

              {data && data.riwayat.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between text-sm text-muted-foreground">
                  <span>
                    Halaman {data.riwayat.current_page} dari {data.riwayat.last_page}
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
                      disabled={page >= data.riwayat.last_page}
                      onClick={() => setPage((p) => p + 1)}
                    >
                      Berikutnya
                    </Button>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="belum-masuk">
          <Card>
            <CardContent className="pt-6">
              <ul className="divide-y divide-border">
                {data?.siswa_belum_masuk.map((s) => (
                  <li key={s.id} className="flex justify-between py-2 text-sm">
                    <span>{s.nama}</span>
                    <span className="text-muted-foreground">{s.kelas?.nama_kelas}</span>
                  </li>
                ))}
                {data?.siswa_belum_masuk.length === 0 && (
                  <li className="py-2 text-sm text-muted-foreground">Semua siswa sudah masuk.</li>
                )}
              </ul>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="belum-pulang">
          <Card>
            <CardContent className="pt-6">
              <ul className="divide-y divide-border">
                {data?.siswa_belum_pulang.map((s) => (
                  <li key={s.id} className="flex justify-between py-2 text-sm">
                    <span>{s.nama}</span>
                    <span className="text-muted-foreground">{s.kelas?.nama_kelas}</span>
                  </li>
                ))}
                {data?.siswa_belum_pulang.length === 0 && (
                  <li className="py-2 text-sm text-muted-foreground">Tidak ada yang belum pulang.</li>
                )}
              </ul>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}

function RiwayatRow({
  absensi,
  busy,
  onUpdateStatus,
  onUpdateWaktu,
  onDestroy,
}: {
  absensi: Absensi;
  busy: boolean;
  onUpdateStatus: (a: Absensi, status: string) => void;
  onUpdateWaktu: (a: Absensi, jamMasuk: string, jamPulang: string) => void;
  onDestroy: (a: Absensi) => void;
}) {
  const [open, setOpen] = useState(false);
  const [status, setStatus] = useState(absensi.status);
  const [jamMasuk, setJamMasuk] = useState(absensi.jam_masuk?.slice(0, 5) ?? "");
  const [jamPulang, setJamPulang] = useState(absensi.jam_pulang?.slice(0, 5) ?? "");

  function save() {
    if (status !== absensi.status) onUpdateStatus(absensi, status);
    if (jamMasuk !== (absensi.jam_masuk?.slice(0, 5) ?? "") || jamPulang !== (absensi.jam_pulang?.slice(0, 5) ?? "")) {
      onUpdateWaktu(absensi, jamMasuk, jamPulang);
    }
    setOpen(false);
  }

  return (
    <TableRow>
      <TableCell className="font-medium">{absensi.siswa?.nama}</TableCell>
      <TableCell>{absensi.siswa?.kelas?.nama_kelas}</TableCell>
      <TableCell>{absensi.jam_masuk ?? "-"}</TableCell>
      <TableCell>{absensi.jam_pulang ?? "-"}</TableCell>
      <TableCell>
        <div className="flex items-center gap-1.5">
          <Badge variant="secondary" className="capitalize">{absensi.status}</Badge>
          {Boolean(absensi.terlambat) && <Badge variant="destructive">Terlambat</Badge>}
        </div>
      </TableCell>
      <TableCell className="text-xs uppercase text-muted-foreground">{absensi.sumber}</TableCell>
      <TableCell className="text-right">
        <div className="flex justify-end gap-2">
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={<Button variant="outline" size="sm">Edit</Button>} />
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Edit Absensi — {absensi.siswa?.nama}</DialogTitle>
              </DialogHeader>
              <div className="space-y-4">
                <div>
                  <Label>Status</Label>
                  <Select value={status} onValueChange={(v) => setStatus((v ?? "hadir") as Absensi["status"])}>
                    <SelectTrigger className="w-full"><SelectValue>{(v: string) => STATUS_LABELS[v] ?? v}</SelectValue></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="hadir">Hadir</SelectItem>
                      <SelectItem value="izin">Izin</SelectItem>
                      <SelectItem value="sakit">Sakit</SelectItem>
                      <SelectItem value="alpha">Alpha</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <Label>Jam Masuk</Label>
                    <Input type="time" value={jamMasuk} onChange={(e) => setJamMasuk(e.target.value)} />
                  </div>
                  <div>
                    <Label>Jam Pulang</Label>
                    <Input type="time" value={jamPulang} onChange={(e) => setJamPulang(e.target.value)} />
                  </div>
                </div>
              </div>
              <DialogFooter>
                <Button onClick={save} disabled={busy}>Simpan</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
          <Button variant="ghost" size="sm" onClick={() => onDestroy(absensi)} disabled={busy}>
            Hapus
          </Button>
        </div>
      </TableCell>
    </TableRow>
  );
}
