"use client";

import { useEffect, useState } from "react";
import { apiFetch, ApiError } from "@/lib/api";
import type { Kelas, Siswa } from "@/lib/types";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { PageHeader } from "@/components/page-header";

interface ManualFormData {
  cutoff_masuk: string;
  kelas_list: Kelas[];
  siswa_list: Siswa[];
}

const STATUS_LABELS: Record<string, string> = {
  hadir: "Hadir",
  izin: "Izin",
  sakit: "Sakit",
  alpha: "Alpha",
};

export default function ManualPage() {
  const [formData, setFormData] = useState<ManualFormData | null>(null);
  const [tanggal, setTanggal] = useState(() => new Date().toISOString().slice(0, 10));
  const [siswaId, setSiswaId] = useState("");
  const [jenis, setJenis] = useState<"masuk" | "pulang">("masuk");
  const [jam, setJam] = useState("");
  const [status, setStatus] = useState("hadir");
  const [keterangan, setKeterangan] = useState("");
  const [overrideCutoff, setOverrideCutoff] = useState(false);
  const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    apiFetch<ManualFormData>("/api/absensi/manual").then(setFormData).catch(() => {
      setMessage({ type: "error", text: "Gagal memuat data form." });
    });
  }, []);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setMessage(null);
    setSubmitting(true);
    try {
      await apiFetch("/api/absensi/manual", {
        method: "POST",
        body: JSON.stringify({
          tanggal,
          siswa_id: siswaId,
          jenis,
          jam: jam || undefined,
          status,
          keterangan: keterangan || undefined,
          override_cutoff: overrideCutoff,
        }),
      });
      setMessage({ type: "success", text: `Absensi ${jenis} berhasil disimpan.` });
      setSiswaId("");
      setKeterangan("");
    } catch (err) {
      const text = err instanceof ApiError ? err.message : "Gagal menyimpan absensi.";
      setMessage({ type: "error", text });
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="max-w-xl space-y-6">
      <PageHeader
        eyebrow="Operasional"
        title="Input Absensi Manual"
        description={
          formData ? `Jam operasional masuk: mulai ${formData.cutoff_masuk}` : undefined
        }
      />

      <Card>
        <CardHeader>
          <CardTitle className="text-sm">Form Absensi</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <Label htmlFor="tanggal">Tanggal</Label>
              <Input
                id="tanggal"
                type="date"
                value={tanggal}
                onChange={(e) => setTanggal(e.target.value)}
                required
              />
            </div>

            <div>
              <Label htmlFor="siswa">Siswa</Label>
              <Select value={siswaId} onValueChange={(v) => setSiswaId(v ?? "")} required>
                <SelectTrigger id="siswa" className="w-full">
                  <SelectValue placeholder="Pilih siswa">
                    {() => {
                      const siswa = formData?.siswa_list.find((s) => String(s.id) === siswaId);
                      return siswa ? `${siswa.nama} — ${siswa.kelas?.nama_kelas}` : "Pilih siswa";
                    }}
                  </SelectValue>
                </SelectTrigger>
                <SelectContent>
                  {formData?.siswa_list.map((siswa) => (
                    <SelectItem key={siswa.id} value={String(siswa.id)}>
                      {siswa.nama} — {siswa.kelas?.nama_kelas}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="jenis">Jenis</Label>
                <Select value={jenis} onValueChange={(v) => setJenis((v ?? "masuk") as "masuk" | "pulang")}>
                  <SelectTrigger id="jenis" className="w-full">
                    <SelectValue>{(v: string) => (v === "pulang" ? "Pulang" : "Masuk")}</SelectValue>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="masuk">Masuk</SelectItem>
                    <SelectItem value="pulang">Pulang</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label htmlFor="jam">Jam (opsional)</Label>
                <Input
                  id="jam"
                  type="time"
                  value={jam}
                  onChange={(e) => setJam(e.target.value)}
                />
              </div>
            </div>

            {jenis === "masuk" && (
              <div>
                <Label htmlFor="status">Status</Label>
                <Select value={status} onValueChange={(v) => setStatus(v ?? "hadir")}>
                  <SelectTrigger id="status" className="w-full">
                    <SelectValue>{(v: string) => STATUS_LABELS[v] ?? v}</SelectValue>
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="hadir">Hadir</SelectItem>
                    <SelectItem value="izin">Izin</SelectItem>
                    <SelectItem value="sakit">Sakit</SelectItem>
                    <SelectItem value="alpha">Alpha</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            )}

            <div>
              <Label htmlFor="keterangan">Keterangan (opsional)</Label>
              <Input
                id="keterangan"
                value={keterangan}
                onChange={(e) => setKeterangan(e.target.value)}
                maxLength={255}
              />
            </div>

            {jenis === "masuk" && (
              <label className="flex items-center gap-2 text-sm text-muted-foreground">
                <input
                  type="checkbox"
                  checked={overrideCutoff}
                  onChange={(e) => setOverrideCutoff(e.target.checked)}
                  className="h-4 w-4 rounded border-input"
                />
                Override jam operasional
              </label>
            )}

            {message && (
              <p
                className={`rounded-2xl px-4 py-3 text-sm ${
                  message.type === "success"
                    ? "bg-emerald-50 text-emerald-700"
                    : "bg-red-50 text-red-700"
                }`}
              >
                {message.text}
              </p>
            )}

            <Button type="submit" disabled={submitting || !siswaId} className="w-full">
              {submitting ? "Menyimpan..." : "Simpan Absensi"}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
