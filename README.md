# Dockerfile for Pukiwiki


# Docker Compoes 利用例

1. 構成
```
+- .env
+- docker-compose.yml
+- pukiwiki/
    +- Dockerfile
    +- rootfs/
```

2. docker-compose.yml 
次に docker-compose.yml ファイルの例を示します。

リバースプロキシを利用する際、最前段にて次のヘッダが
適切に設定されている場合は、Pukiwiki 内のアドレスが適宜設定されます。
- X-FORWARDED-PROTO
- X-FORWARDED-HOST
- X-FORWARDED-PORT

必要に応じて、次の環境変数を指定することが可能です。
- PKWK_PROTO: 最前段のプロトコル (http, https)
- PKWK_HOST:  最前段のホスト名
- PKWK_PORT:  最前段のポート番号
- PKWK_PREFIX: パスのプレフィックス

```
services:
  pukiwiki:
    image: pukiwiki:latest
    build: ./pukiwiki/
    ports:
      - 9001:9001
    volumes:
      - pukiwiki_wiki:/var/www/html/wiki
      - pukiwiki_attach:/var/www/html/attach
      - pukiwiki_backup:/var/www/html/backup
      - pukiwiki_counter:/var/www/html/counter
    environment:
      PKWK_PROTO:  <https or http>
      PKWK_HOST:   <web server hostname>
      PKWK_PORT:   <web server port> 
      PKWK_PREFIX: <pukiwiki prefix>

volumes:
  pukiwiki_wiki:
    driver_opts:
      type: none
      device: ${DATA_DIR}/pukiwiki/wiki
      o: bind
  pukiwiki_attach:
    driver_opts:
      type: none
      device: ${DATA_DIR}/pukiwiki/attach
      o: bind
  pukiwiki_backup:
    driver_opts:
      type: none
      device: ${DATA_DIR}/pukiwiki/backup
      o: bind
  pukiwiki_counter:
    driver_opts:
      type: none
      device: ${DATA_DIR}/pukiwiki/counter
      o: bind
```


3. .env 設定
DATA_DIR=<データを保存するホストディレクトリ>
事前に、ディレクトリを生成しておく必要があります。

- bash
```
DATA_DIR=<データを保存するホストディレクトリ>
mkdir -p "${DATA_DIR}"/pukiwiki/{wiki,attach,backup,counter}
```

- bash 以外
```
DATA_DIR=<データを保存するホストディレクトリ>
mkdir -p "${DATA_DIR}/pukiwiki/wiki"
mkdir -p "${DATA_DIR}/pukiwiki/attach"
mkdir -p "${DATA_DIR}/pukiwiki/backup"
mkdir -p "${DATA_DIR}/pukiwiki/counter"
```
