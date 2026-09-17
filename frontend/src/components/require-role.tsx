"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useAuth } from "@/context/auth-context";
import type { User } from "@/lib/types";

export function RequireRole({
  roles,
  children,
}: {
  roles: User["role"][];
  children: React.ReactNode;
}) {
  const { user, loading } = useAuth();
  const router = useRouter();
  const allowed = !!user && roles.includes(user.role);

  useEffect(() => {
    if (!loading && user && !allowed) {
      router.replace("/absensi/monitor");
    }
  }, [loading, user, allowed, router]);

  if (loading || !allowed) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
        Memuat...
      </div>
    );
  }

  return <>{children}</>;
}
