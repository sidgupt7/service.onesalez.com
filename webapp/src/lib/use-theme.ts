import { useCallback, useEffect, useState, type SetStateAction } from "react";

function readTheme() {
  try {
    const saved = localStorage.getItem("onesalez-theme");
    return saved
      ? saved === "dark"
      : window.matchMedia("(prefers-color-scheme: dark)").matches;
  } catch {
    return document.documentElement.classList.contains("dark");
  }
}

export function useTheme() {
  const [darkMode, setState] = useState(readTheme);
  useEffect(() => {
    const sync = () => setState(readTheme());
    window.addEventListener("onesalez-theme-change", sync);
    window.addEventListener("storage", sync);
    return () => {
      window.removeEventListener("onesalez-theme-change", sync);
      window.removeEventListener("storage", sync);
    };
  }, []);
  useEffect(() => {
    document.documentElement.classList.toggle("dark", darkMode);
  }, [darkMode]);
  const setDarkMode = useCallback((value: SetStateAction<boolean>) => {
    const next = typeof value === "function" ? value(readTheme()) : value;
    document.documentElement.classList.toggle("dark", next);
    try {
      localStorage.setItem("onesalez-theme", next ? "dark" : "light");
    } catch {
      /* Private browser storage may be unavailable. */
    }
    setState(next);
    window.dispatchEvent(new Event("onesalez-theme-change"));
  }, []);
  return [darkMode, setDarkMode] as const;
}
