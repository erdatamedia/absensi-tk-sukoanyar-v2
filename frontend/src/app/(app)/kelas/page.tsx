"use client";

import { useCallback, useEffect, useState } from "react";
import { apiFetch, ApiError } from "@/lib/api";
import type { Kelas } from "@/lib/types";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
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
import { PageHeader } from "@/components/page-header";

export default function KelasPage() {
  const [kelasList, setKelasList] = useState<Kelas[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const result = await apiFetch<{ kelas_list: Kelas[] }>("/api/kelas");
      setKelasList(result.kelas_list);
      setError(null);
    } catch {
      setError("Gagal memuat daftar kelas.");
    }
  }, []);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- initial load on mount
    load();
  }, [load]);

  async function createKelas(namaKelas: string, tahunAjaran: string) {
    setBusy(true);
    try {
      await apiFetch("/api/kelas", {
        method: "POST",
        body: JSON.stringify({ nama_kelas: namaKelas, tahun_ajaran: tahunAjaran }),
      });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal menambah kelas.");
    } finally {
      setBusy(false);
    }
  }

  async function updateKelas(kelas: Kelas, namaKelas: string, tahunAjaran: string) {
    setBusy(true);
    try {
      await apiFetch(`/api/kelas/${kelas.id}`, {
        method: "PATCH",
        body: JSON.stringify({ nama_kelas: namaKelas, tahun_ajaran: tahunAjaran }),
      });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal memperbarui kelas.");
    } finally {
      setBusy(false);
    }
  }

  async function deleteKelas(kelas: Kelas) {
    if (!confirm(`Hapus kelas ${kelas.nama_kelas}?`)) return;
    setBusy(true);
    try {
      await apiFetch(`/api/kelas/${kelas.id}`, { method: "DELETE" });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal menghapus kelas.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Master Data"
        title="Kelas"
        description="Kelola daftar kelas dan lihat jumlah siswa per kelas."
        actions={
          <KelasFormDialog title="Tambah Kelas" onSubmit={createKelas} busy={busy}>
            <Button size="sm">Tambah Kelas</Button>
          </KelasFormDialog>
        }
      />

      {error && (
        <p className="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>
      )}

      <Card>
        <CardContent className="overflow-x-auto pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nama Kelas</TableHead>
                <TableHead>Tahun Ajaran</TableHead>
                <TableHead>Jumlah Siswa</TableHead>
                <TableHead className="text-right">Aksi</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {kelasList?.length === 0 && (
                <TableRow>
                  <TableCell colSpan={4} className="text-center text-sm text-muted-foreground">
                    Belum ada kelas.
                  </TableCell>
                </TableRow>
              )}
              {kelasList?.map((kelas) => (
                <TableRow key={kelas.id}>
                  <TableCell className="font-medium">{kelas.nama_kelas}</TableCell>
                  <TableCell>{kelas.tahun_ajaran}</TableCell>
                  <TableCell>{kelas.siswa_count ?? 0}</TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      <KelasFormDialog
                        title={`Edit ${kelas.nama_kelas}`}
                        initialNamaKelas={kelas.nama_kelas}
                        initialTahunAjaran={kelas.tahun_ajaran}
                        onSubmit={(nama, tahun) => updateKelas(kelas, nama, tahun)}
                        busy={busy}
                      >
                        <Button variant="outline" size="sm">Edit</Button>
                      </KelasFormDialog>
                      <Button variant="ghost" size="sm" onClick={() => deleteKelas(kelas)} disabled={busy}>
                        Hapus
                      </Button>
                    </div>
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

function KelasFormDialog({
  title,
  initialNamaKelas = "",
  initialTahunAjaran = "",
  onSubmit,
  busy,
  children,
}: {
  title: string;
  initialNamaKelas?: string;
  initialTahunAjaran?: string;
  onSubmit: (namaKelas: string, tahunAjaran: string) => void;
  busy: boolean;
  children: React.ReactNode;
}) {
  const [open, setOpen] = useState(false);
  const [namaKelas, setNamaKelas] = useState(initialNamaKelas);
  const [tahunAjaran, setTahunAjaran] = useState(initialTahunAjaran);

  function submit() {
    onSubmit(namaKelas, tahunAjaran);
    setOpen(false);
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        setOpen(next);
        if (next) {
          setNamaKelas(initialNamaKelas);
          setTahunAjaran(initialTahunAjaran);
        }
      }}
    >
      <DialogTrigger render={children as React.ReactElement} />
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <div>
            <Label>Nama Kelas</Label>
            <Input value={namaKelas} onChange={(e) => setNamaKelas(e.target.value)} maxLength={100} />
          </div>
          <div>
            <Label>Tahun Ajaran</Label>
            <Input
              value={tahunAjaran}
              onChange={(e) => setTahunAjaran(e.target.value)}
              placeholder="2025/2026"
              maxLength={20}
            />
          </div>
        </div>
        <DialogFooter>
          <Button onClick={submit} disabled={busy || !namaKelas || !tahunAjaran}>
            Simpan
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
