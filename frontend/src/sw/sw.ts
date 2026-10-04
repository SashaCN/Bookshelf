import { registerWorker, type WorkerScope } from './worker'

// The entry point of the service worker (built to /sw.js by vite.sw.config.ts, which fills in both constants).
registerWorker(self as unknown as WorkerScope, { version: __VERSION__, precache: __PRECACHE__ })
