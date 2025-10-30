"C:\Program Files (x86)\OpenSSL-Win32\bin\openssl.exe" genrsa -out moreno_homologacion.key 2048
"C:\Program Files (x86)\OpenSSL-Win32\bin\openssl.exe" req -new -key moreno_homologacion.key -subj "/C=AR/O=MORENOHOMOLOGACION/CN=MORENOHOMOLOGACION/serialNumber=CUIT 20355274684" -out moreno_homologacion.csr
pause