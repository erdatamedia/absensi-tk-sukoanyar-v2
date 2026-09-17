import { cn } from "@/lib/utils";

export function GlassHero({
  children,
  className,
}: {
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <section
      className={cn(
        "overflow-hidden rounded-[28px] bg-slate-900 px-6 py-6 text-white shadow-sm sm:px-8",
        className
      )}
    >
      {children}
    </section>
  );
}

export function GlassTile({
  label,
  value,
  hint,
  tone = "default",
}: {
  label: string;
  value: React.ReactNode;
  hint?: string;
  tone?: "default" | "positive";
}) {
  return (
    <div
      className={cn(
        "rounded-3xl border p-4",
        tone === "positive"
          ? "border-emerald-400/20 bg-emerald-400/10"
          : "border-white/10 bg-white/10"
      )}
    >
      <p className={cn("text-sm", tone === "positive" ? "text-emerald-100" : "text-slate-300")}>
        {label}
      </p>
      <p className="mt-2 text-2xl font-semibold text-white sm:text-3xl">{value}</p>
      {hint && (
        <p className={cn("mt-1 text-xs", tone === "positive" ? "text-emerald-100/90" : "text-slate-300")}>
          {hint}
        </p>
      )}
    </div>
  );
}
