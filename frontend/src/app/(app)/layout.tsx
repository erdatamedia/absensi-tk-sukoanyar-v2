"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import {
  Building2,
  ClipboardList,
  History,
  Menu,
  QrCode,
  Settings,
  SquarePen,
  Users,
  X,
  type LucideIcon,
} from "lucide-react";
import { useAuth } from "@/context/auth-context";
import { Button } from "@/components/ui/button";

interface NavItem {
  href: string;
  label: string;
  icon: LucideIcon;
}

interface NavSection {
  label: string;
  items: NavItem[];
}

const NAV_SECTIONS: NavSection[] = [
  {
    label: "Operasional Absensi",
    items: [
      { href: "/absensi/scan", label: "Scan Absensi", icon: QrCode },
      { href: "/absensi/manual", label: "Input Manual", icon: SquarePen },
      { href: "/absensi/monitor", label: "Monitor", icon: ClipboardList },
      { href: "/absensi/riwayat", label: "Riwayat", icon: History },
      { href: "/absensi/rekap", label: "Rekap", icon: ClipboardList },
    ],
  },
  {
    label: "Master Data",
    items: [
      { href: "/siswa", label: "Data Siswa", icon: Users },
      { href: "/kelas", label: "Data Kelas", icon: Building2 },
    ],
  },
  {
    label: "Pengaturan",
    items: [{ href: "/pengaturan", label: "Pengaturan Sekolah", icon: Settings }],
  },
];

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const { user, loading, logout } = useAuth();
  const router = useRouter();
  const pathname = usePathname();
  const [drawerOpen, setDrawerOpen] = useState(false);

  useEffect(() => {
    if (!loading && !user) {
      router.replace("/login");
    }
  }, [loading, user, router]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- close mobile drawer on navigation
    setDrawerOpen(false);
  }, [pathname]);

  if (loading || !user) {
    return (
      <div className="flex min-h-screen items-center justify-center text-sm text-muted-foreground">
        Memuat...
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background lg:flex">
      <Sidebar pathname={pathname} userName={user.name} userEmail={user.email} onLogout={logout} />

      {drawerOpen && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div className="fixed inset-0 bg-black/30" onClick={() => setDrawerOpen(false)} />
          <aside className="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-xl">
            <div className="flex items-center justify-between border-b border-border px-5 py-5">
              <div>
                <p className="text-sm font-semibold text-foreground">Absensi TK</p>
                <p className="text-xs text-muted-foreground">Sistem Absensi TK</p>
              </div>
              <button
                onClick={() => setDrawerOpen(false)}
                className="rounded-xl p-2 text-muted-foreground transition hover:bg-accent"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <NavSections pathname={pathname} />
            <div className="border-t border-border p-4">
              <UserFooter userName={user.name} userEmail={user.email} onLogout={logout} />
            </div>
          </aside>
        </div>
      )}

      <div className="min-w-0 flex-1">
        <div className="sticky top-0 z-30 border-b border-border/80 bg-white/95 backdrop-blur lg:hidden">
          <div className="flex items-center justify-between px-4 py-3">
            <button
              onClick={() => setDrawerOpen(true)}
              className="inline-flex items-center justify-center rounded-xl border border-border p-2 text-muted-foreground transition hover:bg-accent"
            >
              <Menu className="h-5 w-5" />
            </button>
            <div className="text-center">
              <p className="text-sm font-semibold text-foreground">Absensi TK</p>
              <p className="text-xs text-muted-foreground">Sistem Absensi TK</p>
            </div>
            <div className="w-9" />
          </div>
        </div>

        <main className="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">{children}</main>
      </div>
    </div>
  );
}

function Sidebar({
  pathname,
  userName,
  userEmail,
  onLogout,
}: {
  pathname: string;
  userName: string;
  userEmail: string;
  onLogout: () => void;
}) {
  return (
    <div className="hidden lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-72 lg:flex-col lg:border-r lg:border-border lg:bg-white">
      <div className="border-b border-border px-6 py-6">
        <span className="block text-sm font-semibold text-foreground">Absensi TK</span>
        <span className="mt-0.5 block text-xs text-muted-foreground">Sistem Absensi TK</span>
      </div>
      <NavSections pathname={pathname} />
      <div className="border-t border-border p-4">
        <UserFooter userName={userName} userEmail={userEmail} onLogout={onLogout} />
      </div>
    </div>
  );
}

function NavSections({ pathname }: { pathname: string }) {
  return (
    <nav className="flex-1 space-y-6 overflow-y-auto px-4 py-6">
      {NAV_SECTIONS.map((section) => (
        <div key={section.label}>
          <p className="px-3.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">
            {section.label}
          </p>
          <div className="mt-2 space-y-1.5">
            {section.items.map((item) => {
              const active = pathname.startsWith(item.href);
              const Icon = item.icon;
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={`group flex items-center gap-3 rounded-2xl px-3.5 py-3 text-sm font-medium transition ${
                    active
                      ? "bg-slate-900 text-white shadow-sm"
                      : "text-muted-foreground hover:bg-accent hover:text-foreground"
                  }`}
                >
                  <Icon className="h-5 w-5 shrink-0" />
                  <span>{item.label}</span>
                </Link>
              );
            })}
          </div>
        </div>
      ))}
    </nav>
  );
}

function UserFooter({
  userName,
  userEmail,
  onLogout,
}: {
  userName: string;
  userEmail: string;
  onLogout: () => void;
}) {
  return (
    <div className="rounded-2xl bg-muted p-4">
      <div className="mb-4 flex items-center gap-3">
        <span className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-900 text-sm font-semibold text-white">
          {userName.slice(0, 1).toUpperCase()}
        </span>
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold text-foreground">{userName}</p>
          <p className="truncate text-xs text-muted-foreground">{userEmail}</p>
        </div>
      </div>
      <Button variant="outline" className="w-full" onClick={onLogout}>
        Keluar
      </Button>
    </div>
  );
}
