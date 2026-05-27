# 🔒 WSL2 멀티 PHP 환경 — localhost HTTPS(SSL) 구축 가이드

## 아키텍처 개요

이 프로젝트는 **하나의 Nginx가 PHP 버전별 컨테이너를 포트로 구분해 라우팅**하는 구조입니다.

```
브라우저 (Windows)
    │
    │  HTTPS (443 → 포트별 분기)
    ▼
 [Nginx :alpine]
    ├── :8074 → php74 컨테이너 (PHP 7.4)
    ├── :8082 → php82 컨테이너 (PHP 8.2)
    ├── :8083 → php83 컨테이너 (PHP 8.3)
    └── :8084 → (예비 슬롯)
```

각 PHP 버전은 독립된 컨테이너로 격리되어 있고, SSL 인증서는 Nginx 컨테이너 하나에만 마운트하면 됩니다.
포트만 바꾸면 PHP 7.4 레거시 프로젝트와 8.x 최신 프로젝트를 동시에 띄워 개발할 수 있습니다.

---

## 목차

1. [mkcert 설치 (WSL 우분투 터미널)](#1-mkcert-설치-wsl-우분투-터미널)
2. [로컬 인증서 발급 (WSL 우분투 터미널)](#2-로컬-인증서-발급-wsl-우분투-터미널)
3. [Windows 브라우저에 신뢰 등록](#3-windows-브라우저에-신뢰-등록-자물쇠-필수)
4. [컨테이너 올리기](#4-컨테이너-올리기)

---

## 1. mkcert 설치 `WSL 우분투 터미널`

`mkcert`는 로컬 전용 인증서를 뚝딱 만들어주는 도구입니다.
아래 명령어는 **WSL 우분투 터미널에서** 실행합니다.

```bash
# 의존성 설치
sudo apt-get install -y certutil

# mkcert 바이너리 받기
sudo curl -Lo /usr/local/bin/mkcert \
  https://github.com/FiloSottile/mkcert/releases/download/v1.4.4/mkcert-v1.4.4-linux-amd64

# 실행 권한 주기
sudo chmod +x /usr/local/bin/mkcert

# 내 로컬 환경을 인증 기관(CA)으로 등록 — 처음 한 번만 하면 됩니다
sudo mkcert -install
```

---

## 2. 로컬 인증서 발급 `WSL 우분투 터미널`

Nginx 컨테이너가 SSL 인증서를 읽어갈 폴더를 만들고, `localhost` 용 인증서를 구워냅니다.
`docker-compose.yml`에서 `./nginx/ssl:/etc/nginx/ssl`로 마운트되어 있으므로 경로를 맞춰서 생성합니다.

```bash
# 프로젝트 SSL 폴더 생성
mkdir -p ~/platbread/nginx/ssl
cd ~/platbread/nginx/ssl

# 인증서 발급
mkcert localhost 127.0.0.1 ::1
```

발급 완료 후 폴더 안에 두 파일이 생깁니다.

| 파일 | 역할 |
|---|---|
| `localhost+2.pem` | SSL 인증서 (공개) |
| `localhost+2-key.pem` | SSL 개인 키 (비밀) |

이 두 파일을 Nginx `default.conf`에서 `ssl_certificate` / `ssl_certificate_key`로 각각 연결해주면 됩니다.

---

## 3. Windows 브라우저에 신뢰 등록 ★자물쇠 필수★

> WSL 안에서 만든 인증서를 Windows 브라우저(크롬, 웨일 등)는 아직 모릅니다.
> 이 단계를 건너뛰면 아무리 HTTPS를 붙여도 **"주의 요함"** 경고가 뜹니다.

### 3-1. CA 루트 경로 확인 `WSL 우분투 터미널`

```bash
mkcert -CAROOT
# 출력 예시: /home/gom214/.local/share/mkcert
```

### 3-2. Windows 탐색기에서 해당 폴더 열기

`Win + R` 을 누르고 아래 경로를 입력합니다. (사용자명은 본인 환경에 맞게 수정)

```
\\wsl$\Ubuntu\home\gom214\.local\share\mkcert
```

### 3-3. 파일 확장자 변경

`rootCA.pem` 파일명을 `rootCA.crt` 로 바꿉니다.

### 3-4. 인증서 설치

`rootCA.crt` 를 우클릭 → **인증서 설치** 클릭

1. **저장소 위치** → `로컬 컴퓨터` 선택 → **다음** (권한 팝업 뜨면 **예**)
2. **인증서 저장소** → ⚠️ 기본값(자동 선택) **해제** → **모든 인증서를 다음 저장소에 저장** 체크
3. **[찾아보기]** → 목록 맨 위 **신뢰할 수 있는 루트 인증 기관** 선택 → **확인**
4. 설치 완료 후 **열려 있는 브라우저를 전부 끄고 다시 시작**

---

## 4. 컨테이너 올리기

```bash
docker compose down && docker compose up -d
```

정상적으로 뜨면 `https://127.0.0.1:8082` 처럼 포트 번호로 각 PHP 버전에 바로 접근할 수 있습니다.

| 주소 | PHP 버전 |
|---|---|
| `https://127.0.0.1:8074` | PHP 7.4 |
| `https://127.0.0.1:8082` | PHP 8.2 |
| `https://127.0.0.1:8083` | PHP 8.3 |
| `https://127.0.0.1:8084` | 예비 |

---

> **팀원 온보딩 시 주의.** 인증서 등록(3단계)은 각자의 PC에서 개별적으로 진행해야 합니다.
> mkcert로 발급한 인증서는 기본 10년 유효이므로 자주 반복할 필요는 없습니다.