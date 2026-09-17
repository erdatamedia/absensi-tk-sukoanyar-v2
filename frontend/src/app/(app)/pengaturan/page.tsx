"use client";

import { useEffect, useRef, useState } from "react";
import { apiFetch, ApiError } from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { PageHeader } from "@/components/page-header";
import { RequireRole } from "@/components/require-role";

interface SchoolSettings {
  school_name: string;
  school_tagline: string;
  school_logo_url: string | null;
  operational_start: string;
  operational_end: string;
}

export default function PengaturanPage() {
  return (
    <RequireRole roles={["admin"]}>
      <PengaturanPageContent />
    </RequireRole>
  );
}

function PengaturanPageContent() {
  const [settings, setSettings] = useState<SchoolSettings | null>(null);
  const [schoolName, setSchoolName] = useState("");
  const [schoolTagline, setSchoolTagline] = useState("");
  const [operationalStart, setOperationalStart] = useState("");
  const [operationalEnd, setOperationalEnd] = useState("");
  const [removeLogo, setRemoveLogo] = useState(false);
  const [logoPreview, setLogoPreview] = useState<string | null>(null);
  const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    apiFetch<{ settings: SchoolSettings }>("/api/settings/school")
      .then(({ settings }) => {
        setSettings(settings);
        setSchoolName(settings.school_name);
        setSchoolTagline(settings.school_tagline);
        setOperationalStart(settings.operational_start);
        setOperationalEnd(settings.operational_end);
      })
      .catch(() => setMessage({ type: "error", text: "Gagal memuat pengaturan sekolah." }));
  }, []);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setMessage(null);
    setSubmitting(true);
    try {
      const formData = new FormData();
      formData.append("school_name", schoolName);
      formData.append("school_tagline", schoolTagline);
      formData.append("operational_start", operationalStart);
      formData.append("operational_end", operationalEnd);
      if (removeLogo) formData.append("remove_logo", "1");
      const file = fileInputRef.current?.files?.[0];
      if (file) formData.append("school_logo", file);

      const result = await apiFetch<{ settings: SchoolSettings }>("/api/settings/school", {
        method: "POST",
        body: formData,
      });
      setSettings(result.settings);
      setRemoveLogo(false);
      setLogoPreview(null);
      if (fileInputRef.current) fileInputRef.current.value = "";
      setMessage({ type: "success", text: "Pengaturan sekolah berhasil diperbarui." });
    } catch (err) {
      setMessage({
        type: "error",
        text: err instanceof ApiError ? err.message : "Gagal menyimpan pengaturan.",
      });
    } finally {
      setSubmitting(false);
    }
  }

  const currentLogo = removeLogo ? null : logoPreview ?? settings?.school_logo_url ?? null;

  return (
    <div className="max-w-xl space-y-6">
      <PageHeader
        eyebrow="Pengaturan"
        title="Pengaturan Sekolah"
        description="Identitas sekolah dan jam operasional absensi masuk."
      />

      <Card>
        <CardHeader>
          <CardTitle className="text-sm">Identitas & Jam Operasional</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <Label htmlFor="school_name">Nama Sekolah</Label>
              <Input
                id="school_name"
                value={schoolName}
                onChange={(e) => setSchoolName(e.target.value)}
                maxLength={120}
                required
              />
            </div>

            <div>
              <Label htmlFor="school_tagline">Tagline (opsional)</Label>
              <Input
                id="school_tagline"
                value={schoolTagline}
                onChange={(e) => setSchoolTagline(e.target.value)}
                maxLength={120}
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="operational_start">Jam Masuk Mulai</Label>
                <Input
                  id="operational_start"
                  type="time"
                  value={operationalStart}
                  onChange={(e) => setOperationalStart(e.target.value)}
                  required
                />
              </div>
              <div>
                <Label htmlFor="operational_end">Jam Masuk Selesai</Label>
                <Input
                  id="operational_end"
                  type="time"
                  value={operationalEnd}
                  onChange={(e) => setOperationalEnd(e.target.value)}
                  required
                />
              </div>
            </div>

            <div>
              <Label>Logo Sekolah</Label>
              {currentLogo && (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={currentLogo} alt="Logo sekolah" className="mb-2 h-16 w-16 rounded object-contain" />
              )}
              <input
                ref={fileInputRef}
                type="file"
                accept="image/png,image/jpeg,image/webp"
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) {
                    setRemoveLogo(false);
                    setLogoPreview(URL.createObjectURL(file));
                  }
                }}
                className="block w-full text-sm text-muted-foreground"
              />
              {settings?.school_logo_url && (
                <label className="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
                  <input
                    type="checkbox"
                    checked={removeLogo}
                    onChange={(e) => setRemoveLogo(e.target.checked)}
                    className="h-4 w-4 rounded border-input"
                  />
                  Hapus logo saat ini
                </label>
              )}
            </div>

            {message && (
              <p
                className={`rounded-2xl px-4 py-3 text-sm ${
                  message.type === "success" ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-700"
                }`}
              >
                {message.text}
              </p>
            )}

            <Button type="submit" disabled={submitting} className="w-full">
              {submitting ? "Menyimpan..." : "Simpan Pengaturan"}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
