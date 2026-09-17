"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/context/auth-context";

export default function Home() {
  const { user, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.replace("/login");
    } else {
      router.replace(user.role === "orang_tua" ? "/portal-ortu" : "/absensi/monitor");
    }
  }, [loading, user, router]);

  return (
    <div className="flex min-h-screen items-center justify-center text-sm text-muted-foreground">
      Memuat...
    </div>
  );
}
