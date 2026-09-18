"use client";

import { useEffect, useRef, useState } from "react";
import { apiFetch, ApiError } from "@/lib/api";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { PageHeader } from "@/components/page-header";
import { SwitchCamera } from "lucide-react";

type FacingMode = "user" | "environment";

interface ScanResult {
  status: string;
  siswa_id: number;
  nama: string;
  can_masuk: boolean;
  can_pulang: boolean;
  already_complete: boolean;
  jam_masuk: string | null;
  jam_pulang: string | null;
  score?: number;
}

interface RecognizeResponse {
  status: "ok" | "no_match" | "service_unavailable" | "error";
  siswa_id?: number;
  nama?: string;
  can_masuk?: boolean;
  can_pulang?: boolean;
  already_complete?: boolean;
  score?: number;
  msg?: string;
}

type Mode = "face" | "qr";
type Step = "scanning" | "confirm" | "saving" | "done";

const RECOGNIZE_INTERVAL_MS = 1200;

function captureFrame(video: HTMLVideoElement, canvas: HTMLCanvasElement): string | null {
  if (!video.videoWidth || !video.videoHeight) return null;
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext("2d");
  if (!ctx) return null;
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
  return canvas.toDataURL("image/jpeg", 0.72);
}

export default function ScanPage() {
  const [mode, setMode] = useState<Mode>("face");
  const [step, setStep] = useState<Step>("scanning");
  const [scanResult, setScanResult] = useState<ScanResult | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [photoPreview, setPhotoPreview] = useState<string | null>(null);
  const [cameraError, setCameraError] = useState<string | null>(null);
  const [faceServiceDown, setFaceServiceDown] = useState(false);
  const [searching, setSearching] = useState(false);

  return (
    <div className="mx-auto max-w-md space-y-4">
      <PageHeader
        eyebrow="Operasional"
        title="Scan Absensi"
        description={
          mode === "face"
            ? "Arahkan wajah ke kamera untuk absen otomatis."
            : "Arahkan kamera ke kartu QR siswa."
        }
        actions={
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              setMode((m) => (m === "face" ? "qr" : "face"));
              setMessage(null);
              setCameraError(null);
            }}
            disabled={step !== "scanning"}
          >
            {mode === "face" ? "Gunakan QR" : "Gunakan Wajah"}
          </Button>
        }
      />

      {faceServiceDown && mode === "face" && (
        <p className="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-700">
          Layanan pengenalan wajah sedang tidak aktif. Gunakan tombol &quot;Gunakan QR&quot; di atas
          untuk tetap bisa absen.
        </p>
      )}

      {mode === "face" ? (
        <FaceScanner
          active={step === "scanning"}
          onMatch={(result, photo) => {
            setScanResult(result);
            setPhotoPreview(photo);
            setStep("confirm");
          }}
          onServiceDown={(down) => setFaceServiceDown(down)}
          onCameraError={setCameraError}
          onSearching={setSearching}
        />
      ) : (
        <QrScanner
          active={step === "scanning"}
          onMatch={(result, photo) => {
            setScanResult(result);
            setPhotoPreview(photo);
            setStep("confirm");
          }}
          onMessage={setMessage}
          onCameraError={setCameraError}
        />
      )}

      {cameraError && (
        <p className="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700">{cameraError}</p>
      )}

      {mode === "face" && step === "scanning" && !cameraError && (
        <p className="text-center text-sm text-muted-foreground">
          {searching ? "Mencari wajah yang cocok..." : "Kamera aktif."}
        </p>
      )}

      {message && step === "scanning" && (
        <p className="rounded-2xl bg-muted px-4 py-3 text-sm text-foreground">{message}</p>
      )}

      {step === "confirm" && scanResult && photoPreview && (
        <ConfirmCard
          scanResult={scanResult}
          photoPreview={photoPreview}
          onSaved={(text) => {
            setMessage(text);
            setStep("done");
            setTimeout(() => {
              setScanResult(null);
              setPhotoPreview(null);
              setStep("scanning");
            }, 2500);
          }}
          onSaving={() => setStep("saving")}
          onCancel={() => {
            setScanResult(null);
            setPhotoPreview(null);
            setStep("scanning");
          }}
        />
      )}

      {step === "saving" && (
        <div className="flex items-center justify-center gap-2 py-4">
          <Badge variant="secondary">Menyimpan...</Badge>
        </div>
      )}

      {step === "done" && message && (
        <p className="rounded-2xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{message}</p>
      )}
    </div>
  );
}

function ConfirmCard({
  scanResult,
  photoPreview,
  onSaved,
  onSaving,
  onCancel,
}: {
  scanResult: ScanResult;
  photoPreview: string;
  onSaved: (message: string) => void;
  onSaving: () => void;
  onCancel: () => void;
}) {
  async function confirmSave(jenis: "masuk" | "pulang") {
    onSaving();
    try {
      const result = await apiFetch<{ status: string; nama: string; msg?: string }>(
        "/api/absensi/simpan",
        {
          method: "POST",
          body: JSON.stringify({
            siswa_id: scanResult.siswa_id,
            foto: photoPreview,
            jenis,
          }),
        }
      );

      if (result.status === "ok") {
        onSaved(`Absensi ${jenis} untuk ${result.nama} berhasil disimpan.`);
      } else {
        onSaved(result.msg ?? "Gagal menyimpan absensi.");
      }
    } catch (err) {
      onSaved(err instanceof ApiError ? err.message : "Gagal menyimpan absensi.");
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-sm">Konfirmasi — {scanResult.nama}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src={photoPreview} alt="Foto absensi" className="w-full rounded-2xl" />
        {typeof scanResult.score === "number" && (
          <p className="text-xs text-muted-foreground">
            Kecocokan wajah: {(scanResult.score * 100).toFixed(0)}%
          </p>
        )}
        <div className="flex gap-2">
          {scanResult.can_masuk && (
            <Button className="flex-1" onClick={() => confirmSave("masuk")}>
              Absen Masuk
            </Button>
          )}
          {scanResult.can_pulang && (
            <Button className="flex-1" onClick={() => confirmSave("pulang")}>
              Absen Pulang
            </Button>
          )}
        </div>
        <Button variant="outline" className="w-full" onClick={onCancel}>
          Batal
        </Button>
      </CardContent>
    </Card>
  );
}

function FaceScanner({
  active,
  onMatch,
  onServiceDown,
  onCameraError,
  onSearching,
}: {
  active: boolean;
  onMatch: (result: ScanResult, photo: string) => void;
  onServiceDown: (down: boolean) => void;
  onCameraError: (error: string | null) => void;
  onSearching: (searching: boolean) => void;
}) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const busyRef = useRef(false);
  const activeRef = useRef(active);
  const onMatchRef = useRef(onMatch);
  const onServiceDownRef = useRef(onServiceDown);
  const [facing, setFacing] = useState<FacingMode>("user");

  useEffect(() => {
    activeRef.current = active;
  });
  useEffect(() => {
    onMatchRef.current = onMatch;
    onServiceDownRef.current = onServiceDown;
  });

  useEffect(() => {
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
        onCameraError(null);
      } catch (err) {
        onCameraError(err instanceof Error ? err.message : "Gagal mengakses kamera.");
      }
    }

    start();

    const interval = setInterval(async () => {
      if (!activeRef.current || busyRef.current) return;
      const video = videoRef.current;
      const canvas = canvasRef.current;
      if (!video || !canvas) return;

      const frame = captureFrame(video, canvas);
      if (!frame) return;

      busyRef.current = true;
      onSearching(true);
      try {
        const result = await apiFetch<RecognizeResponse>("/api/absensi/recognize-face", {
          method: "POST",
          body: JSON.stringify({ foto: frame }),
        });

        if (result.status === "service_unavailable") {
          onServiceDownRef.current(true);
        } else {
          onServiceDownRef.current(false);
        }

        if (result.status === "ok" && result.siswa_id) {
          onMatchRef.current(
            {
              status: "ok",
              siswa_id: result.siswa_id,
              nama: result.nama ?? "",
              can_masuk: Boolean(result.can_masuk),
              can_pulang: Boolean(result.can_pulang),
              already_complete: Boolean(result.already_complete),
              jam_masuk: null,
              jam_pulang: null,
              score: result.score,
            },
            frame
          );
        }
      } catch {
        // Silently retry on next interval tick — a single failed poll shouldn't interrupt the kiosk.
      } finally {
        busyRef.current = false;
        onSearching(false);
      }
    }, RECOGNIZE_INTERVAL_MS);

    return () => {
      cancelled = true;
      clearInterval(interval);
      streamRef.current?.getTracks().forEach((t) => t.stop());
      streamRef.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [facing]);

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle className="text-sm">Kamera</CardTitle>
        <Button
          variant="ghost"
          size="sm"
          onClick={() => setFacing((f) => (f === "user" ? "environment" : "user"))}
        >
          <SwitchCamera className="size-4" />
          {facing === "user" ? "Kamera Depan" : "Kamera Belakang"}
        </Button>
      </CardHeader>
      <CardContent>
        <video ref={videoRef} muted playsInline className="w-full rounded-2xl bg-slate-900" />
        <canvas ref={canvasRef} className="hidden" />
      </CardContent>
    </Card>
  );
}

function QrScanner({
  active,
  onMatch,
  onMessage,
  onCameraError,
}: {
  active: boolean;
  onMatch: (result: ScanResult, photo: string) => void;
  onMessage: (message: string | null) => void;
  onCameraError: (error: string | null) => void;
}) {
  const readerRef = useRef<HTMLDivElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const scannerRef = useRef<import("html5-qrcode").Html5Qrcode | null>(null);
  const activeRef = useRef(active);
  const handleScanSuccessRef = useRef<(qrToken: string) => void>(() => {});

  useEffect(() => {
    activeRef.current = active;
  });

  function capturePhoto(): string | null {
    const video = readerRef.current?.querySelector("video") as HTMLVideoElement | null;
    const canvas = canvasRef.current;
    if (!video || !canvas) return null;
    return captureFrame(video, canvas);
  }

  async function handleScanSuccess(qrToken: string) {
    if (!activeRef.current) return;
    try {
      await scannerRef.current?.pause(true);
    } catch {
      // ignore pause errors
    }

    try {
      const result = await apiFetch<ScanResult>("/api/absensi/scan", {
        method: "POST",
        body: JSON.stringify({ qr_token: qrToken }),
      });

      if (result.status !== "ok") {
        onMessage("Siswa tidak ditemukan.");
        resumeScanning();
        return;
      }

      if (result.already_complete) {
        onMessage(`${result.nama} sudah absen masuk & pulang hari ini.`);
        resumeScanning();
        return;
      }

      const photo = capturePhoto();
      if (!photo) {
        onMessage("Gagal mengambil foto. Coba lagi.");
        resumeScanning();
        return;
      }

      onMatch(result, photo);
    } catch {
      onMessage("Gagal memproses QR. Coba lagi.");
      resumeScanning();
    }
  }

  function resumeScanning() {
    setTimeout(() => {
      scannerRef.current?.resume();
    }, 1500);
  }

  useEffect(() => {
    handleScanSuccessRef.current = handleScanSuccess;
  });

  useEffect(() => {
    let cancelled = false;

    async function start() {
      const { Html5Qrcode } = await import("html5-qrcode");
      if (cancelled || !readerRef.current) return;

      const scanner = new Html5Qrcode(readerRef.current.id);
      scannerRef.current = scanner;

      try {
        const cameras = await Html5Qrcode.getCameras();
        if (!cameras.length) {
          onCameraError("Kamera tidak ditemukan.");
          return;
        }
        const cameraId = cameras[cameras.length - 1].id;

        await scanner.start(
          cameraId,
          { fps: 10, qrbox: { width: 220, height: 220 } },
          (decodedText) => handleScanSuccessRef.current(decodedText),
          () => undefined
        );
      } catch (err) {
        onCameraError(err instanceof Error ? err.message : "Gagal mengakses kamera.");
      }
    }

    start();

    return () => {
      cancelled = true;
      try {
        scannerRef.current
          ?.stop()
          .then(() => scannerRef.current?.clear())
          .catch(() => undefined);
      } catch {
        // html5-qrcode throws synchronously (not a rejected promise) when
        // stop() is called on a scanner that never finished starting —
        // e.g. camera permission was denied before start() resolved.
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-sm">Kamera</CardTitle>
      </CardHeader>
      <CardContent>
        <div id="reader" ref={readerRef} className="overflow-hidden rounded-2xl" />
        <canvas ref={canvasRef} className="hidden" />
      </CardContent>
    </Card>
  );
}
