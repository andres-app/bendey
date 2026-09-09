//go:build windows

package main

import (
	"crypto/subtle"
	"encoding/base64"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"sort"
	"strings"
	"syscall"
	"time"
	"unsafe"
)

const (
	connectorName    = "TiquePOS Print Connector"
	connectorVersion = "1.0.1"
	connectorCompany = "TIQUEPOS S.A.C."
	connectorRUC     = "20609518597"
	defaultPort      = 17654
)

type Config struct {
	AllowedOrigin string `json:"allowedOrigin"`
	Token         string `json:"token"`
	Port          int    `json:"port"`
	InstalledAt   string `json:"installedAt"`
}

type Printer struct {
	Name      string `json:"name"`
	IsDefault bool   `json:"isDefault"`
}

type PrintRequest struct {
	Printer  string `json:"printer"`
	Data     string `json:"data"`
	Encoding string `json:"encoding"`
	JobName  string `json:"jobName"`
}

type apiResponse map[string]any

var logger *log.Logger

func main() {
	if len(os.Args) >= 2 {
		switch strings.ToLower(os.Args[1]) {
		case "install":
			if err := installCommand(os.Args[2:]); err != nil {
				writeInstallError(err)
				os.Exit(1)
			}
			os.Exit(0)
		case "uninstall":
			if err := uninstallCommand(); err != nil {
				os.Exit(1)
			}
			os.Exit(0)
		case "serve":
			if err := serveCommand(); err != nil {
				appendLog("fatal: " + err.Error())
				os.Exit(1)
			}
			return
		}
	}

	// Ejecutar sin argumentos inicia el servicio cuando ya está instalado.
	if _, err := os.Stat(configPath()); err == nil {
		_ = serveCommand()
		return
	}
}

func installCommand(args []string) error {
	var origin, token string
	for i := 0; i < len(args); i++ {
		switch args[i] {
		case "--origin":
			if i+1 < len(args) {
				origin = strings.TrimRight(args[i+1], "/")
				i++
			}
		case "--token":
			if i+1 < len(args) {
				token = strings.TrimSpace(args[i+1])
				i++
			}
		}
	}
	if origin == "" || (!strings.HasPrefix(origin, "https://") && !strings.HasPrefix(origin, "http://")) {
		return errors.New("origen de TiquePOS no válido")
	}
	if len(token) < 32 {
		return errors.New("token de vinculación no válido")
	}

	dir := installDir()
	if err := os.MkdirAll(dir, 0700); err != nil {
		return err
	}

	current, err := os.Executable()
	if err != nil {
		return err
	}
	target := filepath.Join(dir, "TiquePOSPrintConnector.exe")
	if !samePath(current, target) {
		// Detener una versión anterior antes de reemplazar el ejecutable.
		_ = exec.Command("taskkill.exe", "/IM", "TiquePOSPrintConnector.exe", "/F").Run()
		time.Sleep(250 * time.Millisecond)
		if err := copyFile(current, target); err != nil {
			return fmt.Errorf("copiar conector: %w", err)
		}
	}

	cfg := Config{AllowedOrigin: origin, Token: token, Port: defaultPort, InstalledAt: time.Now().Format(time.RFC3339)}
	if err := writeJSON(configPath(), cfg); err != nil {
		return fmt.Errorf("guardar configuración: %w", err)
	}

	// Inicio automático para el usuario actual; no requiere privilegios de administrador.
	runValue := `"` + target + `" serve`
	reg := exec.Command("reg.exe", "add", `HKCU\Software\Microsoft\Windows\CurrentVersion\Run`, "/v", "TiquePOSPrintConnector", "/t", "REG_SZ", "/d", runValue, "/f")
	if out, err := reg.CombinedOutput(); err != nil {
		return fmt.Errorf("registrar inicio automático: %v (%s)", err, strings.TrimSpace(string(out)))
	}

	if samePath(current, target) {
		// No se espera durante instalación normal, pero evita duplicar instancias.
		appendLog("install invoked from installed binary; keeping current process")
	}
	cmd := exec.Command(target, "serve")
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true, CreationFlags: 0x00000008} // DETACHED_PROCESS
	if err := cmd.Start(); err != nil {
		return fmt.Errorf("iniciar conector: %w", err)
	}
	return nil
}

func uninstallCommand() error {
	_ = exec.Command("reg.exe", "delete", `HKCU\Software\Microsoft\Windows\CurrentVersion\Run`, "/v", "TiquePOSPrintConnector", "/f").Run()
	_ = exec.Command("taskkill.exe", "/IM", "TiquePOSPrintConnector.exe", "/F").Run()
	return nil
}

func serveCommand() error {
	cfg, err := loadConfig()
	if err != nil {
		return err
	}
	if cfg.Port <= 0 {
		cfg.Port = defaultPort
	}
	setupLogger()
	appendLog(fmt.Sprintf("starting %s v%s on 127.0.0.1:%d for %s", connectorName, connectorVersion, cfg.Port, cfg.AllowedOrigin))

	mux := http.NewServeMux()
	mux.HandleFunc("/v1/status", withSecurity(cfg, handleStatus))
	mux.HandleFunc("/v1/printers", withSecurity(cfg, handlePrinters))
	mux.HandleFunc("/v1/print/raw", withSecurity(cfg, handlePrintRaw))

	srv := &http.Server{
		Addr:              fmt.Sprintf("127.0.0.1:%d", cfg.Port),
		Handler:           mux,
		ReadHeaderTimeout: 5 * time.Second,
		ReadTimeout:       10 * time.Second,
		WriteTimeout:      15 * time.Second,
		IdleTimeout:       30 * time.Second,
	}
	return srv.ListenAndServe()
}

func withSecurity(cfg Config, next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		origin := strings.TrimRight(r.Header.Get("Origin"), "/")
		if origin == "" || subtle.ConstantTimeCompare([]byte(origin), []byte(strings.TrimRight(cfg.AllowedOrigin, "/"))) != 1 {
			http.Error(w, "Origen no autorizado", http.StatusForbidden)
			return
		}

		w.Header().Set("Access-Control-Allow-Origin", cfg.AllowedOrigin)
		w.Header().Set("Vary", "Origin")
		w.Header().Set("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type, X-TiquePOS-Token")
		w.Header().Set("Access-Control-Allow-Private-Network", "true")
		w.Header().Set("Cache-Control", "no-store")

		if r.Method == http.MethodOptions {
			w.WriteHeader(http.StatusNoContent)
			return
		}

		token := r.Header.Get("X-TiquePOS-Token")
		if len(token) != len(cfg.Token) || subtle.ConstantTimeCompare([]byte(token), []byte(cfg.Token)) != 1 {
			writeAPI(w, http.StatusUnauthorized, apiResponse{"ok": false, "error": "Conector no vinculado con este navegador."})
			return
		}
		next(w, r)
	}
}

func handleStatus(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		writeAPI(w, http.StatusMethodNotAllowed, apiResponse{"ok": false})
		return
	}
	host, _ := os.Hostname()
	writeAPI(w, http.StatusOK, apiResponse{
		"ok":         true,
		"name":       connectorName,
		"version":    connectorVersion,
		"machine":    host,
		"platform":   "windows",
		"company":    connectorCompany,
		"ruc":        connectorRUC,
		"product":    connectorName,
		"executable": "TiquePOSPrintConnector.exe",
	})
}

func handlePrinters(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		writeAPI(w, http.StatusMethodNotAllowed, apiResponse{"ok": false})
		return
	}
	printers, err := enumPrinters()
	if err != nil {
		writeAPI(w, http.StatusInternalServerError, apiResponse{"ok": false, "error": err.Error()})
		return
	}
	writeAPI(w, http.StatusOK, apiResponse{"ok": true, "printers": printers})
}

func handlePrintRaw(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeAPI(w, http.StatusMethodNotAllowed, apiResponse{"ok": false})
		return
	}
	r.Body = http.MaxBytesReader(w, r.Body, 4*1024*1024)
	defer r.Body.Close()
	var req PrintRequest
	dec := json.NewDecoder(r.Body)
	if err := dec.Decode(&req); err != nil {
		writeAPI(w, http.StatusBadRequest, apiResponse{"ok": false, "error": "Solicitud de impresión inválida."})
		return
	}
	req.Printer = strings.TrimSpace(req.Printer)
	if req.Printer == "" {
		writeAPI(w, http.StatusBadRequest, apiResponse{"ok": false, "error": "Falta seleccionar la impresora."})
		return
	}
	if req.JobName == "" {
		req.JobName = "TiquePOS - Etiquetas"
	}

	var data []byte
	var err error
	switch strings.ToLower(req.Encoding) {
	case "base64":
		data, err = base64.StdEncoding.DecodeString(req.Data)
	case "utf8", "utf-8", "":
		data = []byte(req.Data)
	default:
		err = errors.New("codificación no soportada")
	}
	if err != nil {
		writeAPI(w, http.StatusBadRequest, apiResponse{"ok": false, "error": err.Error()})
		return
	}
	if len(data) == 0 {
		writeAPI(w, http.StatusBadRequest, apiResponse{"ok": false, "error": "El trabajo está vacío."})
		return
	}

	jobID, written, err := rawPrint(req.Printer, req.JobName, data)
	if err != nil {
		appendLog("print error: " + err.Error())
		writeAPI(w, http.StatusInternalServerError, apiResponse{"ok": false, "error": err.Error()})
		return
	}
	appendLog(fmt.Sprintf("printed job=%d printer=%s bytes=%d", jobID, req.Printer, written))
	writeAPI(w, http.StatusOK, apiResponse{"ok": true, "jobId": jobID, "written": written})
}

func writeAPI(w http.ResponseWriter, status int, payload apiResponse) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(payload)
}

func loadConfig() (Config, error) {
	var cfg Config
	b, err := os.ReadFile(configPath())
	if err != nil {
		return cfg, fmt.Errorf("configuración no encontrada: %w", err)
	}
	if err := json.Unmarshal(b, &cfg); err != nil {
		return cfg, err
	}
	if cfg.AllowedOrigin == "" || cfg.Token == "" {
		return cfg, errors.New("configuración incompleta")
	}
	return cfg, nil
}

func installDir() string {
	base := os.Getenv("LOCALAPPDATA")
	if base == "" {
		base = filepath.Join(os.Getenv("USERPROFILE"), "AppData", "Local")
	}
	return filepath.Join(base, "TiquePOS", "PrintConnector")
}
func configPath() string { return filepath.Join(installDir(), "config.json") }
func logPath() string    { return filepath.Join(installDir(), "connector.log") }

func setupLogger() {
	_ = os.MkdirAll(installDir(), 0700)
	f, err := os.OpenFile(logPath(), os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0600)
	if err == nil {
		logger = log.New(f, "", log.LstdFlags)
	}
}
func appendLog(s string) {
	if logger != nil {
		logger.Println(s)
		return
	}
	_ = os.MkdirAll(installDir(), 0700)
	f, err := os.OpenFile(logPath(), os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0600)
	if err == nil {
		defer f.Close()
		_, _ = fmt.Fprintf(f, "%s %s\n", time.Now().Format(time.RFC3339), s)
	}
}
func writeInstallError(err error) { appendLog("install error: " + err.Error()) }

func writeJSON(path string, v any) error {
	b, err := json.MarshalIndent(v, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(path, b, 0600)
}
func copyFile(src, dst string) error {
	in, err := os.Open(src)
	if err != nil {
		return err
	}
	defer in.Close()
	out, err := os.Create(dst)
	if err != nil {
		return err
	}
	_, cpErr := io.Copy(out, in)
	closeErr := out.Close()
	if cpErr != nil {
		return cpErr
	}
	return closeErr
}
func samePath(a, b string) bool {
	aa, _ := filepath.Abs(a)
	bb, _ := filepath.Abs(b)
	return strings.EqualFold(aa, bb)
}

// --- Windows Print Spooler ---

var (
	winspool               = syscall.NewLazyDLL("winspool.drv")
	procEnumPrintersW      = winspool.NewProc("EnumPrintersW")
	procGetDefaultPrinterW = winspool.NewProc("GetDefaultPrinterW")
	procOpenPrinterW       = winspool.NewProc("OpenPrinterW")
	procClosePrinter       = winspool.NewProc("ClosePrinter")
	procStartDocPrinterW   = winspool.NewProc("StartDocPrinterW")
	procEndDocPrinter      = winspool.NewProc("EndDocPrinter")
	procStartPagePrinter   = winspool.NewProc("StartPagePrinter")
	procEndPagePrinter     = winspool.NewProc("EndPagePrinter")
	procWritePrinter       = winspool.NewProc("WritePrinter")
)

const (
	printerEnumLocal       = 0x00000002
	printerEnumConnections = 0x00000004
)

type printerInfo4 struct {
	PrinterName *uint16
	ServerName  *uint16
	Attributes  uint32
	_pad        uint32
}

type docInfo1 struct {
	DocName    *uint16
	OutputFile *uint16
	DataType   *uint16
}

func enumPrinters() ([]Printer, error) {
	flags := uintptr(printerEnumLocal | printerEnumConnections)
	var needed, returned uint32
	procEnumPrintersW.Call(flags, 0, 4, 0, 0, uintptr(unsafe.Pointer(&needed)), uintptr(unsafe.Pointer(&returned)))
	if needed == 0 {
		return []Printer{}, nil
	}
	buf := make([]byte, needed)
	r1, _, callErr := procEnumPrintersW.Call(flags, 0, 4, uintptr(unsafe.Pointer(&buf[0])), uintptr(needed), uintptr(unsafe.Pointer(&needed)), uintptr(unsafe.Pointer(&returned)))
	if r1 == 0 {
		return nil, fmt.Errorf("no se pudieron enumerar impresoras: %v", callErr)
	}

	def, _ := getDefaultPrinter()
	size := unsafe.Sizeof(printerInfo4{})
	result := make([]Printer, 0, returned)
	seen := map[string]bool{}
	for i := uint32(0); i < returned; i++ {
		p := (*printerInfo4)(unsafe.Add(unsafe.Pointer(&buf[0]), uintptr(i)*size))
		if p.PrinterName == nil {
			continue
		}
		name := utf16PtrToString(p.PrinterName)
		name = strings.TrimSpace(name)
		if name == "" || seen[strings.ToLower(name)] {
			continue
		}
		seen[strings.ToLower(name)] = true
		result = append(result, Printer{Name: name, IsDefault: strings.EqualFold(name, def)})
	}
	sort.Slice(result, func(i, j int) bool {
		if result[i].IsDefault != result[j].IsDefault {
			return result[i].IsDefault
		}
		return strings.ToLower(result[i].Name) < strings.ToLower(result[j].Name)
	})
	return result, nil
}

func utf16PtrToString(ptr *uint16) string {
	if ptr == nil {
		return ""
	}
	vals := make([]uint16, 0, 64)
	base := unsafe.Pointer(ptr)
	for i := uintptr(0); i < 32768; i++ {
		v := *(*uint16)(unsafe.Add(base, i*2))
		if v == 0 {
			break
		}
		vals = append(vals, v)
	}
	return syscall.UTF16ToString(vals)
}

func getDefaultPrinter() (string, error) {
	var needed uint32
	procGetDefaultPrinterW.Call(0, uintptr(unsafe.Pointer(&needed)))
	if needed == 0 {
		return "", nil
	}
	buf := make([]uint16, needed)
	r1, _, callErr := procGetDefaultPrinterW.Call(uintptr(unsafe.Pointer(&buf[0])), uintptr(unsafe.Pointer(&needed)))
	if r1 == 0 {
		return "", callErr
	}
	return syscall.UTF16ToString(buf), nil
}

func rawPrint(printerName, jobName string, data []byte) (uint32, uint32, error) {
	namePtr, err := syscall.UTF16PtrFromString(printerName)
	if err != nil {
		return 0, 0, err
	}
	var handle syscall.Handle
	r1, _, callErr := procOpenPrinterW.Call(uintptr(unsafe.Pointer(namePtr)), uintptr(unsafe.Pointer(&handle)), 0)
	if r1 == 0 {
		return 0, 0, fmt.Errorf("no se pudo abrir la impresora %q: %v", printerName, callErr)
	}
	defer procClosePrinter.Call(uintptr(handle))

	docNamePtr, _ := syscall.UTF16PtrFromString(jobName)
	rawPtr, _ := syscall.UTF16PtrFromString("RAW")
	info := docInfo1{DocName: docNamePtr, DataType: rawPtr}
	job, _, callErr := procStartDocPrinterW.Call(uintptr(handle), 1, uintptr(unsafe.Pointer(&info)))
	if job == 0 {
		return 0, 0, fmt.Errorf("Windows no pudo iniciar el trabajo RAW: %v", callErr)
	}
	defer procEndDocPrinter.Call(uintptr(handle))

	r1, _, callErr = procStartPagePrinter.Call(uintptr(handle))
	if r1 == 0 {
		return uint32(job), 0, fmt.Errorf("no se pudo iniciar la página: %v", callErr)
	}
	defer procEndPagePrinter.Call(uintptr(handle))

	var written uint32
	r1, _, callErr = procWritePrinter.Call(uintptr(handle), uintptr(unsafe.Pointer(&data[0])), uintptr(uint32(len(data))), uintptr(unsafe.Pointer(&written)))
	if r1 == 0 {
		return uint32(job), written, fmt.Errorf("no se pudo enviar el trabajo a la impresora: %v", callErr)
	}
	if int(written) != len(data) {
		return uint32(job), written, fmt.Errorf("impresión incompleta: %d de %d bytes", written, len(data))
	}
	return uint32(job), written, nil
}
