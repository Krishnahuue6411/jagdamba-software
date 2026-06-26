const http = require("http");
const fs = require("fs");
const path = require("path");
const { spawn } = require("child_process");

const root = __dirname;
const publicDir = path.join(root, "public");
const dbScript = path.join(root, "db.ps1");
const configPath = path.join(root, "config.json");

// Load config
let config = {};
try {
  if (fs.existsSync(configPath)) {
    config = JSON.parse(fs.readFileSync(configPath, "utf8"));
  }
} catch (err) {
  console.warn("Warning: Could not load config.json, using defaults");
}

function resolveFromApp(value, fallback) {
  const selected = value || fallback;
  return path.isAbsolute(selected) ? selected : path.join(root, selected);
}

const dbPath = process.env.SOFT_DATA_MDB || resolveFromApp(config.dbPath, "data\\SOFT_DATA.mdb");
const port = Number(process.env.PORT || config.port || 4173);

// Ensure data directory exists
const dataDir = path.dirname(dbPath);
if (!fs.existsSync(dataDir)) {
  try {
    fs.mkdirSync(dataDir, { recursive: true });
    console.log(`✅ Created data directory: ${dataDir}`);
  } catch (err) {
    console.warn(`Could not create data directory: ${err.message}`);
  }
}

// Check if database exists
if (!fs.existsSync(dbPath)) {
  console.warn(`\n⚠️  WARNING: Database not found at: ${dbPath}`);
  console.warn(`Please place SOFT_DATA.mdb in the data folder.\n`);
} else {
  console.log(`✅ Database found: ${dbPath}`);
}

const mime = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".ico": "image/x-icon"
};

function readBody(req) {
  return new Promise((resolve, reject) => {
    let data = "";
    req.on("data", chunk => {
      data += chunk;
      if (data.length > 10_000_000) {
        reject(new Error("Request too large"));
        req.destroy();
      }
    });
    req.on("end", () => resolve(data || "{}"));
    req.on("error", reject);
  });
}

function runDb(payload) {
  return new Promise((resolve, reject) => {
    const finalPayload = { dbPath, ...payload };
    const encoded = Buffer.from(JSON.stringify(finalPayload), "utf8").toString("base64");
    
    console.log(`[DB] Action: ${payload.action || 'unknown'}, Table: ${payload.table || 'none'}`);
    
    const child = spawn("powershell.exe", [
      "-NoProfile",
      "-ExecutionPolicy",
      "Bypass",
      "-File",
      dbScript,
      "-Payload",
      encoded
    ], { 
      windowsHide: true,
      timeout: 60000
    });

    let stdout = "";
    let stderr = "";
    
    child.stdout.on("data", d => { stdout += d.toString(); });
    child.stderr.on("data", d => { stderr += d.toString(); });
    
    child.on("error", (err) => {
      reject(new Error(`PowerShell error: ${err.message}`));
    });
    
    child.on("close", (code) => {
      const clean = stdout.trim();
      
      try {
        let jsonStr = clean;
        const jsonMatch = clean.match(/\{.*\}/s);
        if (jsonMatch) {
          jsonStr = jsonMatch[0];
        }
        
        const parsed = JSON.parse(jsonStr);
        if (!parsed.ok) {
          reject(new Error(parsed.error || stderr || `Database command failed (code: ${code})`));
        } else {
          resolve(parsed);
        }
      } catch (err) {
        if (stderr) {
          reject(new Error(stderr));
        } else if (clean) {
          reject(new Error(clean));
        } else {
          reject(new Error(`Database command failed with code ${code}`));
        }
      }
    });
  });
}

function sendJson(res, status, value) {
  res.writeHead(status, { 
    "Content-Type": "application/json; charset=utf-8",
    "Access-Control-Allow-Origin": "*",
    "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
    "Access-Control-Allow-Headers": "Content-Type"
  });
  res.end(JSON.stringify(value));
}

const server = http.createServer(async (req, res) => {
  try {
    if (req.method === "OPTIONS") {
      res.writeHead(200, {
        "Access-Control-Allow-Origin": "*",
        "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
        "Access-Control-Allow-Headers": "Content-Type"
      });
      res.end();
      return;
    }
    
    const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
    
    if (url.pathname.startsWith("/api/")) {
      if (req.method !== "POST") {
        return sendJson(res, 405, { ok: false, error: "POST required" });
      }
      
      const body = await readBody(req);
      let payload = {};
      try {
        payload = body ? JSON.parse(body) : {};
      } catch (err) {
        return sendJson(res, 400, { ok: false, error: "Invalid JSON payload" });
      }
      
      const action = url.pathname.replace("/api/", "");
      const data = await runDb({ action, ...payload });
      return sendJson(res, 200, data);
    }

    let filePath = url.pathname === "/" 
      ? path.join(publicDir, "index.html") 
      : path.join(publicDir, url.pathname);
    
    const normalizedPath = path.normalize(filePath);
    if (!normalizedPath.startsWith(publicDir)) {
      res.writeHead(403);
      return res.end("Forbidden");
    }
    
    fs.access(normalizedPath, fs.constants.F_OK, (err) => {
      if (err) {
        const indexPath = path.join(publicDir, "index.html");
        fs.readFile(indexPath, (err2, data) => {
          if (err2) {
            res.writeHead(404);
            res.end("Not found");
            return;
          }
          res.writeHead(200, { "Content-Type": "text/html; charset=utf-8" });
          res.end(data);
        });
        return;
      }
      
      fs.readFile(normalizedPath, (err, data) => {
        if (err) {
          res.writeHead(500);
          res.end("Server error");
          return;
        }
        const ext = path.extname(normalizedPath);
        res.writeHead(200, { "Content-Type": mime[ext] || "application/octet-stream" });
        res.end(data);
      });
    });
    
  } catch (error) {
    console.error("Server error:", error);
    sendJson(res, 500, { ok: false, error: error.message });
  }
});

server.listen(port, "0.0.0.0", () => {
  console.log("\n========================================");
  console.log("  JAGDAMBA ELECTRICAL M-3");
  console.log("  Management Software");
  console.log("========================================");
  console.log(`  ✅ Server: http://localhost:${port}`);
  console.log(`  📁 Database: ${dbPath}`);
  console.log("========================================");
  console.log("\n  🚀 Server is running!");
  console.log("  📱 Open http://localhost:" + port + " in your browser");
  console.log("  ⚠️  Keep this window open\n");
});

process.on("SIGINT", () => {
  console.log("\nShutting down server...");
  server.close(() => {
    console.log("Server closed.");
    process.exit(0);
  });
});

process.on("uncaughtException", (err) => {
  console.error("Uncaught Exception:", err);
});

process.on("unhandledRejection", (reason, promise) => {
  console.error("Unhandled Rejection at:", promise, "reason:", reason);
});