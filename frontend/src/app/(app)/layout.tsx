"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import {
  Building2,
  FileBarChart,
  History,
  MoreHorizontal,
  ScanFace,
  Settings,
  SquarePen,
  Users,
  Activity,
  LogOut,
  X,
  type LucideIcon,
} from "lucide-react";
import { useAuth } from "@/context/auth-context";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import type { User } from "@/lib/types";

interface NavItem {
  href: string;
  label: string;
  icon: LucideIcon;
}

interface NavSection {
  label: string;
  items: NavItem[];
}

// Guru menjalankan operasional absensi + boleh lihat Data Siswa (read-only di
// halaman itu sendiri); Data Kelas & Pengaturan Sekolah khusus admin.
function getNavSections(role: User["role"]): NavSection[] {
  const sections: NavSection[] = [
    {
      label: "Operasional Absensi",
      items: [
        { href: "/absensi/scan", label: "Scan Absensi", icon: ScanFace },
        { href: "/absensi/manual", label: "Input Manual", icon: SquarePen },
        { href: "/absensi/monitor", label: "Monitor", icon: Activity },
        { href: "/absensi/riwayat", label: "Riwayat", icon: History },
        { href: "/absensi/rekap", label: "Rekap", icon: FileBarChart },
      ],
    },
    {
      label: "Master Data",
      items:
        role === "admin"
          ? [
              { href: "/siswa", label: "Data Siswa", icon: Users },
              { href: "/kelas", label: "Data Kelas", icon: Building2 },
            ]
          : [{ href: "/siswa", label: "Data Siswa", icon: Users }],
    },
  ];

  if (role === "admin") {
    sections.push({
      label: "Pengaturan",
      items: [{ href: "/pengaturan", label: "Pengaturan Sekolah", icon: Settings }],
    });
  }

  return sections;
}

// Primary items pinned to the mobile bottom bar; everything else lives behind "Lainnya".
const BOTTOM_NAV_ITEMS: NavItem[] = [
  { href: "/absensi/scan", label: "Scan", icon: ScanFace },
  { href: "/absensi/monitor", label: "Monitor", icon: Activity },
  { href: "/absensi/riwayat", label: "Riwayat", icon: History },
  { href: "/absensi/rekap", label: "Rekap", icon: FileBarChart },
];

function getMoreNavItems(role: User["role"]): NavItem[] {
  const items: NavItem[] = [
    { href: "/absensi/manual", label: "Input Manual", icon: SquarePen },
    { href: "/siswa", label: "Data Siswa", icon: Users },
  ];

  if (role === "admin") {
    items.push(
      { href: "/kelas", label: "Data Kelas", icon: Building2 },
      { href: "/pengaturan", label: "Pengaturan Sekolah", icon: Settings }
    );
  }

  return items;
}

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const { user, loading, logout } = useAuth();
  const router = useRouter();
  const pathname = usePathname();
  const [moreOpen, setMoreOpen] = useState(false);

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.replace("/login");
    } else if (user.role === "orang_tua") {
      router.replace("/portal-ortu");
    }
  }, [loading, user, router]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- close "more" sheet on navigation
    setMoreOpen(false);
  }, [pathname]);

  if (loading || !user || user.role === "orang_tua") {
    return (
      <div className="flex min-h-screen items-center justify-center text-sm text-muted-foreground">
        Memuat...
      </div>
    );
  }

  return (
    <div className="app-gradient-bg min-h-screen">
      <Sidebar
        pathname={pathname}
        role={user.role}
        userName={user.name}
        userEmail={user.email}
        onLogout={logout}
      />

      <div className="min-w-0 lg:pl-[296px]">
        <div className="sticky top-0 z-30 px-4 pt-4 lg:hidden">
          <div className="flex items-center justify-center rounded-3xl border border-white/60 bg-white/60 px-4 py-3 shadow-sm backdrop-blur-xl">
            <div className="text-center">
              <p className="text-sm font-semibold text-foreground">Absensi TK</p>
              <p className="text-xs text-muted-foreground">Sistem Absensi TK</p>
            </div>
          </div>
        </div>

        <main className="mx-auto w-full max-w-6xl px-4 py-6 pb-28 sm:px-6 lg:px-8 lg:pb-6">
          {children}
        </main>
      </div>

      <BottomNav
        pathname={pathname}
        role={user.role}
        onMoreClick={() => setMoreOpen(true)}
      />

      <MoreSheet
        open={moreOpen}
        onOpenChange={setMoreOpen}
        pathname={pathname}
        role={user.role}
        userName={user.name}
        userEmail={user.email}
        onLogout={logout}
      />
    </div>
  );
}

function Sidebar({
  pathname,
  role,
  userName,
  userEmail,
  onLogout,
}: {
  pathname: string;
  role: User["role"];
  userName: string;
  userEmail: string;
  onLogout: () => void;
}) {
  return (
    <div className="hidden lg:fixed lg:inset-y-4 lg:left-4 lg:flex lg:w-64 lg:flex-col lg:rounded-3xl lg:border lg:border-white/60 lg:bg-white/60 lg:shadow-xl lg:shadow-orange-900/5 lg:backdrop-blur-2xl">
      <div className="border-b border-white/60 px-6 py-6">
        <span className="block text-sm font-semibold text-foreground">Absensi TK</span>
        <span className="mt-0.5 block text-xs text-muted-foreground">Sistem Absensi TK</span>
      </div>
      <NavSections pathname={pathname} role={role} />
      <div className="border-t border-white/60 p-4">
        <UserFooter userName={userName} userEmail={userEmail} onLogout={onLogout} />
      </div>
    </div>
  );
}

function NavSections({ pathname, role }: { pathname: string; role: User["role"] }) {
  const sections = getNavSections(role);

  return (
    <nav className="flex-1 space-y-6 overflow-y-auto px-4 py-6">
      {sections.map((section) => (
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
                      ? "bg-primary text-primary-foreground shadow-sm shadow-orange-500/30"
                      : "text-muted-foreground hover:bg-white/50 hover:text-foreground"
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
    <div className="rounded-2xl border border-white/60 bg-white/50 p-4 backdrop-blur-md">
      <div className="mb-4 flex items-center gap-3">
        <span className="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
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

function BottomNav({
  pathname,
  role,
  onMoreClick,
}: {
  pathname: string;
  role: User["role"];
  onMoreClick: () => void;
}) {
  const moreActive = getMoreNavItems(role).some((item) => pathname.startsWith(item.href));

  return (
    <nav className="fixed inset-x-4 bottom-4 z-30 lg:hidden">
      <div className="flex items-center justify-around rounded-3xl border border-white/60 bg-white/70 px-2 py-2 shadow-lg shadow-orange-900/10 backdrop-blur-2xl">
        {BOTTOM_NAV_ITEMS.map((item) => {
          const active = pathname.startsWith(item.href);
          const Icon = item.icon;
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`flex flex-1 flex-col items-center gap-0.5 rounded-2xl px-2 py-2 text-[11px] font-medium transition ${
                active ? "bg-primary text-primary-foreground" : "text-muted-foreground"
              }`}
            >
              <Icon className="h-5 w-5" />
              <span>{item.label}</span>
            </Link>
          );
        })}
        <button
          onClick={onMoreClick}
          className={`flex flex-1 flex-col items-center gap-0.5 rounded-2xl px-2 py-2 text-[11px] font-medium transition ${
            moreActive ? "bg-primary text-primary-foreground" : "text-muted-foreground"
          }`}
        >
          <MoreHorizontal className="h-5 w-5" />
          <span>Lainnya</span>
        </button>
      </div>
    </nav>
  );
}

function MoreSheet({
  open,
  onOpenChange,
  pathname,
  role,
  userName,
  userEmail,
  onLogout,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  pathname: string;
  role: User["role"];
  userName: string;
  userEmail: string;
  onLogout: () => void;
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent showCloseButton={false} className="lg:hidden">
        <DialogHeader className="flex-row items-center justify-between space-y-0">
          <DialogTitle>Menu Lainnya</DialogTitle>
          <button
            onClick={() => onOpenChange(false)}
            className="rounded-xl p-1.5 text-muted-foreground transition hover:bg-accent"
          >
            <X className="h-4 w-4" />
          </button>
        </DialogHeader>
        <div className="space-y-1.5">
          {getMoreNavItems(role).map((item) => {
            const active = pathname.startsWith(item.href);
            const Icon = item.icon;
            return (
              <Link
                key={item.href}
                href={item.href}
                className={`flex items-center gap-3 rounded-2xl px-3.5 py-3 text-sm font-medium transition ${
                  active
                    ? "bg-primary text-primary-foreground"
                    : "text-muted-foreground hover:bg-accent hover:text-foreground"
                }`}
              >
                <Icon className="h-5 w-5 shrink-0" />
                <span>{item.label}</span>
              </Link>
            );
          })}
        </div>
        <div className="mt-2 flex items-center gap-3 rounded-2xl border border-border p-3">
          <span className="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
            {userName.slice(0, 1).toUpperCase()}
          </span>
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-semibold text-foreground">{userName}</p>
            <p className="truncate text-xs text-muted-foreground">{userEmail}</p>
          </div>
          <button
            onClick={onLogout}
            className="flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium text-destructive hover:bg-destructive/10"
          >
            <LogOut className="h-4 w-4" />
            Keluar
          </button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
