import app from "flarum/forum/app";

export interface ProfileTab {
  id: string;
  path: string;
  label?: string;
  apiProtected?: boolean;
}

export function automaticTabId(path: string): string {
  const bytes = new TextEncoder().encode(path);
  return (
    "auto_" +
    btoa(String.fromCharCode(...bytes))
      .replace(/\+/g, "-")
      .replace(/\//g, "_")
      .replace(/=+$/, "")
  );
}

export function pathMatches(template: string, suffix: string): boolean {
  const pattern = template
    .split("/")
    .map((segment) => {
      if (/^:[\w]+\.\.\.$/.test(segment)) return ".+";
      if (/^:[\w]+$/.test(segment)) return "[^/]+";
      return segment.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    })
    .join("/");
  return new RegExp("^" + pattern + "/?$").test(suffix);
}

export function profileTab(path: string): string | undefined {
  const match = path.match(/\/u\/[^/]+(\/[^?#]*)?/);
  if (!match) return undefined;
  const suffix = match[1]?.replace(/\/$/, "") || "";
  return app.forum
    .attribute<ProfileTab[]>("profilePrivacyTabs")
    ?.slice()
    .sort(
      (a, b) =>
        Number(a.path.includes(":")) - Number(b.path.includes(":")) ||
        b.path.length - a.path.length,
    )
    .find((tab) => pathMatches(tab.path, suffix))?.id;
}

export function isProtectedTab(path: string): boolean {
  const tab = profileTab(path);
  return (
    !!tab &&
    (app.forum.attribute<string[]>("profileHiddenTabs") || []).includes(tab)
  );
}
