"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { apiFetch, apiFileUrl, ApiError } from "@/lib/api";
import { useAuth } from "@/context/auth-context";
import type { Kelas, Siswa } from "@/lib/types";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { PageHeader } from "@/components/page-header";
import { SwitchCamera } from "lucide-react";

type FacingMode = "user" | "environment";
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

interface SiswaResponse {
  status: string;
  siswa_list: Siswa[];
  kelas_list: Kelas[];
}

const ALL = "__all__";

export default function SiswaPage() {
  const { user } = useAuth();
  const isAdmin = user?.role === "admin";
  const [kelasId, setKelasId] = useState(ALL);
  const [q, setQ] = useState("");
  const [data, setData] = useState<SiswaResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const importInputRef = useRef<HTMLInputElement>(null);

  const load = useCallback(async () => {
    try {
      const params = new URLSearchParams();
      if (kelasId !== ALL) params.set("kelas_id", kelasId);
      if (q) params.set("q", q);
      const result = await apiFetch<SiswaResponse>(`/api/siswa?${params}`);
      setData(result);
      setError(null);
    } catch {
      setError("Gagal memuat data siswa.");
    }
  }, [kelasId, q]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- refetch when filters change
    load();
  }, [load]);

  async function createSiswa(payload: SiswaFormValues) {
    setBusy(true);
    try {
      await apiFetch("/api/siswa", { method: "POST", body: JSON.stringify(payload) });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal menambah siswa.");
    } finally {
      setBusy(false);
    }
  }

  async function updateSiswa(siswa: Siswa, payload: SiswaFormValues) {
    setBusy(true);
    try {
      await apiFetch(`/api/siswa/${siswa.id}`, { method: "PATCH", body: JSON.stringify(payload) });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal memperbarui siswa.");
    } finally {
      setBusy(false);
    }
  }

  async function enrollFace(siswa: Siswa, file: File) {
    setBusy(true);
    setError(null);
    try {
      const formData = new FormData();
      formData.append("foto", file);
      await apiFetch(`/api/siswa/${siswa.id}/foto-referensi`, {
        method: "POST",
        body: formData,
      });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal mendaftarkan wajah.");
    } finally {
      setBusy(false);
    }
  }

  async function deleteSiswa(siswa: Siswa) {
    if (!confirm(`Hapus data siswa ${siswa.nama}?`)) return;
    setBusy(true);
    try {
      await apiFetch(`/api/siswa/${siswa.id}`, { method: "DELETE" });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal menghapus siswa.");
    } finally {
      setBusy(false);
    }
  }

  async function handleImport(file: File) {
    setBusy(true);
    setMessage(null);
    try {
      const formData = new FormData();
      formData.append("file", file);
      const result = await apiFetch<{ created: number; updated: number; skipped: number }>(
        "/api/siswa/import",
        { method: "POST", body: formData }
      );
      setMessage(`Impor selesai. ${result.created} baru, ${result.updated} diperbarui, ${result.skipped} dilewati.`);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Gagal mengimpor file.");
    } finally {
      setBusy(false);
      if (importInputRef.current) importInputRef.current.value = "";
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow="Master Data"
        title="Data Siswa"
        description={
          isAdmin
            ? "Kelola data siswa, cetak kartu QR, dan impor data massal."
            : "Lihat data siswa dan cetak kartu QR."
        }
        actions={
          <>
            <a href={apiFileUrl("/siswa/kartu-pdf/massal")} target="_blank" rel="noreferrer">
              <Button variant="outline" size="sm">Cetak Semua Kartu</Button>
            </a>
            {isAdmin && (
              <>
                <Button variant="outline" size="sm" onClick={() => importInputRef.current?.click()} disabled={busy}>
                  Impor Excel
                </Button>
                <input
                  ref={importInputRef}
                  type="file"
                  accept=".xlsx,.xls,.csv"
                  className="hidden"
                  onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) handleImport(file);
                  }}
                />
                <SiswaFormDialog title="Tambah Siswa" kelasList={data?.kelas_list ?? []} onSubmit={createSiswa} busy={busy}>
                  <Button size="sm">Tambah Siswa</Button>
                </SiswaFormDialog>
              </>
            )}
          </>
        }
      />

      <Card>
        <CardContent className="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2">
          <div>
            <Label>Cari Nama/NIS</Label>
            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari..." />
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

      {message && (
        <p className="rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{message}</p>
      )}
      {error && (
        <p className="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p>
      )}

      <Card>
        <CardContent className="overflow-x-auto pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>NIS</TableHead>
                <TableHead>Nama</TableHead>
                <TableHead>Kelas</TableHead>
                <TableHead>Jenis Kelamin</TableHead>
                <TableHead>Wajah</TableHead>
                <TableHead className="text-right">Aksi</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data?.siswa_list.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className="text-center text-sm text-muted-foreground">
                    Tidak ada data siswa.
                  </TableCell>
                </TableRow>
              )}
              {data?.siswa_list.map((siswa) => (
                <TableRow key={siswa.id}>
                  <TableCell>{siswa.nis}</TableCell>
                  <TableCell className="font-medium">{siswa.nama}</TableCell>
                  <TableCell>{siswa.kelas?.nama_kelas}</TableCell>
                  <TableCell>{siswa.jenis_kelamin === "L" ? "Laki-laki" : "Perempuan"}</TableCell>
                  <TableCell>
                    {siswa.foto_referensi ? (
                      <div className="flex items-center gap-2">
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img
                          src={apiFileUrl(`/storage/${siswa.foto_referensi}`)}
                          alt={`Foto wajah ${siswa.nama}`}
                          className="h-9 w-9 rounded-full border object-cover"
                        />
                        <Badge variant="secondary" className="bg-emerald-100 text-emerald-700">
                          Terdaftar
                        </Badge>
                      </div>
                    ) : (
                      <Badge variant="outline">Belum</Badge>
                    )}
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      <a href={apiFileUrl(`/siswa/${siswa.id}/kartu-pdf`)} target="_blank" rel="noreferrer">
                        <Button variant="outline" size="sm">Kartu</Button>
                      </a>
                      {isAdmin && (
                        <>
                          <FaceEnrollDialog siswa={siswa} onSubmit={(file) => enrollFace(siswa, file)} busy={busy}>
                            <Button variant="outline" size="sm">Wajah</Button>
                          </FaceEnrollDialog>
                          <SiswaFormDialog
                            title={`Edit ${siswa.nama}`}
                            kelasList={data?.kelas_list ?? []}
                            initial={siswa}
                            onSubmit={(payload) => updateSiswa(siswa, payload)}
                            busy={busy}
                          >
                            <Button variant="outline" size="sm">Edit</Button>
                          </SiswaFormDialog>
                          <Button variant="ghost" size="sm" onClick={() => deleteSiswa(siswa)} disabled={busy}>
                            Hapus
                          </Button>
                        </>
                      )}
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

interface SiswaFormValues {
  nis: string;
  nama: string;
  kelas_id: string;
  jenis_kelamin: "L" | "P";
  tanggal_lahir?: string;
  alamat?: string;
  keterangan?: string;
}

function SiswaFormDialog({
  title,
  kelasList,
  initial,
  onSubmit,
  busy,
  children,
}: {
  title: string;
  kelasList: Kelas[];
  initial?: Siswa;
  onSubmit: (payload: SiswaFormValues) => void;
  busy: boolean;
  children: React.ReactNode;
}) {
  const [open, setOpen] = useState(false);
  const [nis, setNis] = useState(initial?.nis ?? "");
  const [nama, setNama] = useState(initial?.nama ?? "");
  const [kelasId, setKelasId] = useState(initial?.kelas_id ? String(initial.kelas_id) : "");
  const [jenisKelamin, setJenisKelamin] = useState<"L" | "P">(initial?.jenis_kelamin ?? "L");
  const [tanggalLahir, setTanggalLahir] = useState(initial?.tanggal_lahir ?? "");
  const [alamat, setAlamat] = useState(initial?.alamat ?? "");
  const [keterangan, setKeterangan] = useState(initial?.keterangan ?? "");

  function resetFromInitial() {
    setNis(initial?.nis ?? "");
    setNama(initial?.nama ?? "");
    setKelasId(initial?.kelas_id ? String(initial.kelas_id) : "");
    setJenisKelamin(initial?.jenis_kelamin ?? "L");
    setTanggalLahir(initial?.tanggal_lahir ?? "");
    setAlamat(initial?.alamat ?? "");
    setKeterangan(initial?.keterangan ?? "");
  }

  function submit() {
    onSubmit({
      nis,
      nama,
      kelas_id: kelasId,
      jenis_kelamin: jenisKelamin,
      tanggal_lahir: tanggalLahir || undefined,
      alamat: alamat || undefined,
      keterangan: keterangan || undefined,
    });
    setOpen(false);
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        setOpen(next);
        if (next) resetFromInitial();
      }}
    >
      <DialogTrigger render={children as React.ReactElement} />
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label>NIS</Label>
              <Input value={nis} onChange={(e) => setNis(e.target.value)} maxLength={50} />
            </div>
            <div>
              <Label>Jenis Kelamin</Label>
              <Select value={jenisKelamin} onValueChange={(v) => setJenisKelamin((v ?? "L") as "L" | "P")}>
                <SelectTrigger className="w-full">
                  <SelectValue>{(v: string) => (v === "P" ? "Perempuan" : "Laki-laki")}</SelectValue>
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="L">Laki-laki</SelectItem>
                  <SelectItem value="P">Perempuan</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <div>
            <Label>Nama</Label>
            <Input value={nama} onChange={(e) => setNama(e.target.value)} maxLength={100} />
          </div>
          <div>
            <Label>Kelas</Label>
            <Select value={kelasId} onValueChange={(v) => setKelasId(v ?? "")}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder="Pilih kelas">
                  {() => kelasList.find((k) => String(k.id) === kelasId)?.nama_kelas ?? "Pilih kelas"}
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                {kelasList.map((k) => (
                  <SelectItem key={k.id} value={String(k.id)}>{k.nama_kelas}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label>Tanggal Lahir (opsional)</Label>
            <Input type="date" value={tanggalLahir ?? ""} onChange={(e) => setTanggalLahir(e.target.value)} />
          </div>
          <div>
            <Label>Alamat (opsional)</Label>
            <Input value={alamat ?? ""} onChange={(e) => setAlamat(e.target.value)} maxLength={255} />
          </div>
          <div>
            <Label>Keterangan (opsional)</Label>
            <Input value={keterangan ?? ""} onChange={(e) => setKeterangan(e.target.value)} maxLength={255} />
          </div>
        </div>
        <DialogFooter>
          <Button onClick={submit} disabled={busy || !nis || !nama || !kelasId}>
            Simpan
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function dataUrlToFile(dataUrl: string, filename: string): File {
  const [header, base64] = dataUrl.split(",");
  const mime = header.match(/:(.*?);/)?.[1] ?? "image/jpeg";
  const binary = atob(base64);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
  return new File([bytes], filename, { type: mime });
}

type FaceEnrollMode = "upload" | "kamera";

function FaceEnrollDialog({
  siswa,
  onSubmit,
  busy,
  children,
}: {
  siswa: Siswa;
  onSubmit: (file: File) => void;
  busy: boolean;
  children: React.ReactNode;
}) {
  const [open, setOpen] = useState(false);
  const [mode, setMode] = useState<FaceEnrollMode>("kamera");
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);

  const existingPhotoUrl = siswa.foto_referensi
    ? apiFileUrl(`/storage/${siswa.foto_referensi}`)
    : null;

  function resetCapture() {
    setFile(null);
    setPreview(null);
  }

  function submit() {
    if (!file) return;
    onSubmit(file);
    setOpen(false);
    resetCapture();
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        setOpen(next);
        if (!next) resetCapture();
        else setMode("kamera");
      }}
    >
      <DialogTrigger render={children as React.ReactElement} />
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Daftarkan Wajah — {siswa.nama}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <p className="text-sm text-muted-foreground">
            Ambil atau unggah 1 foto wajah yang jelas dan menghadap depan. Foto ini dipakai
            sistem untuk mengenali siswa saat absensi wajah di kiosk.
          </p>

          <div className="flex gap-2">
            <Button
              type="button"
              size="sm"
              variant={mode === "kamera" ? "default" : "outline"}
              onClick={() => {
                setMode("kamera");
                resetCapture();
              }}
            >
              Buka Kamera
            </Button>
            <Button
              type="button"
              size="sm"
              variant={mode === "upload" ? "default" : "outline"}
              onClick={() => {
                setMode("upload");
                resetCapture();
              }}
            >
              Unggah File
            </Button>
          </div>

          {mode === "kamera" ? (
            <CameraCapture
              preview={preview}
              onCapture={(dataUrl) => {
                setPreview(dataUrl);
                setFile(dataUrlToFile(dataUrl, `${siswa.nis || siswa.id}-wajah.jpg`));
              }}
              onRetake={resetCapture}
            />
          ) : (
            <>
              {(preview ?? existingPhotoUrl) && (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  src={preview ?? existingPhotoUrl ?? undefined}
                  alt={`Foto referensi ${siswa.nama}`}
                  className="h-40 w-40 rounded-2xl object-cover"
                />
              )}
              <input
                type="file"
                accept="image/png,image/jpeg"
                onChange={(e) => {
                  const selected = e.target.files?.[0];
                  if (selected) {
                    setFile(selected);
                    setPreview(URL.createObjectURL(selected));
                  }
                }}
                className="block w-full text-sm text-muted-foreground"
              />
            </>
          )}
        </div>
        <DialogFooter>
          <Button onClick={submit} disabled={busy || !file}>
            Simpan Wajah
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function CameraCapture({
  preview,
  onCapture,
  onRetake,
}: {
  preview: string | null;
  onCapture: (dataUrl: string) => void;
  onRetake: () => void;
}) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const [cameraError, setCameraError] = useState<string | null>(null);
  const [facing, setFacing] = useState<FacingMode>("user");

  useEffect(() => {
    if (preview) return;
    let cancelled = false;

    async function start() {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: facing } });
        if (cancelled) {
          stream.getTracks().forEach((t) => t.stop());
          return;
        }
        streamRef.current = stream;
        if (videoRef.current) {
          videoRef.current.srcObject = stream;
          await videoRef.current.play();
        }
        setCameraError(null);
      } catch (err) {
        setCameraError(err instanceof Error ? err.message : "Gagal mengakses kamera.");
      }
    }

    start();

    return () => {
      cancelled = true;
      streamRef.current?.getTracks().forEach((t) => t.stop());
      streamRef.current = null;
    };
  }, [preview, facing]);

  function capture() {
    const video = videoRef.current;
    const canvas = canvasRef.current;
    if (!video || !canvas || !video.videoWidth || !video.videoHeight) return;
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    onCapture(canvas.toDataURL("image/jpeg", 0.85));
  }

  if (preview) {
    return (
      <div className="space-y-3">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={preview} alt="Hasil tangkapan wajah" className="h-48 w-48 rounded-2xl object-cover" />
        <Button type="button" size="sm" variant="outline" onClick={onRetake}>
          Ambil Ulang
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {cameraError ? (
        <p className="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700">{cameraError}</p>
      ) : (
        <video ref={videoRef} muted playsInline className="w-full rounded-2xl bg-slate-900" />
      )}
      <canvas ref={canvasRef} className="hidden" />
      <div className="flex gap-2">
        <Button type="button" size="sm" onClick={capture} disabled={!!cameraError}>
          Ambil Foto
        </Button>
        <Button
          type="button"
          size="sm"
          variant="outline"
          onClick={() => setFacing((f) => (f === "user" ? "environment" : "user"))}
        >
          <SwitchCamera className="size-4" />
          {facing === "user" ? "Kamera Depan" : "Kamera Belakang"}
        </Button>
      </div>
    </div>
  );
}
