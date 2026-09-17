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
        "glass-hero overflow-hidden rounded-[28px] px-6 py-6 text-white shadow-lg shadow-indigo-500/20 sm:px-8",
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
        "rounded-3xl border p-4 backdrop-blur-md",
        tone === "positive"
          ? "border-emerald-200/50 bg-emerald-300/20"
          : "border-white/40 bg-white/15"
      )}
    >
      <p className={cn("text-sm", tone === "positive" ? "text-emerald-50" : "text-white/85")}>
        {label}
      </p>
      <p className="mt-2 text-2xl font-semibold text-white sm:text-3xl">{value}</p>
      {hint && (
        <p className={cn("mt-1 text-xs", tone === "positive" ? "text-emerald-50/90" : "text-white/80")}>
          {hint}
        </p>
      )}
    </div>
  );
}
