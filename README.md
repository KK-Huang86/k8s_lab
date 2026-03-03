# Kubernetes Lab

跟隨YouTube 教學[二十一分鐘略懂 Kubernetes (以及 Helm)](https://www.youtube.com/watch?v=RUjcGn2YeVo&t=412s)實作的練習專案，透過 Minikube 實際操作 Kubernetes 的核心概念：叢集節點、Pod、ConfigMap、Secret、Service、Ingress，以及跨 namespace 的 Pod 互通。

---

## 涵蓋概念

- **Node / Cluster** — 透過 Minikube 建立單節點叢集
- **Pod** — Kubernetes 最小的可部署單位
- **Deployment** — 管理一組複本 Pod（本專案設定 3 個副本）
- **ConfigMap** — 存放非敏感設定（USERNAME），以環境變數注入 Pod
- **Secret** — 存放敏感資料（PASSWORD），以 base64 編碼後注入
- **Service** — 在叢集內部暴露 Pod，同 namespace 下可直接以 Service 名稱互通
- **Ingress** — 依路徑將外部 HTTP 流量導入叢集，也是跨 namespace 呼叫的橋樑
- **Helm** — 將上述資源模板化，一份 chart 透過不同 values 部署到多個 namespace

---

## 專案結構

```
k8s_lab/
  app/
    Dockerfile          # PHP + Apache 映像，執行時讀取環境變數
    src/index.php       # 印出伺服器資訊、MESSAGE、USERNAME、PASSWORD
                        # 接受 ?url= 參數，在 Pod 內部代理呼叫其他服務
  resources/            # 原始 YAML 清單（namespace 1 和 2 各一份）
    po1.yaml / po2.yaml
    po1-svc.yaml / po2-svc.yaml
    deploy1.yaml / deploy2.yaml
    deploy1-svc.yaml / deploy2-svc.yaml
    cm1.yaml / cm2.yaml
    secret1.yaml / secret2.yaml
    ing1.yaml / ing2.yaml
  helm_chart/           # 以 Helm 模板化的相同資源
    templates/
    values.yaml         # nsId=1, replicaCount=3
    values-ns2.yaml     # nsId=2
  imgs/
    screenshopt_for_k8slab.png
```

---

## 事前準備

- [Minikube](https://minikube.sigs.k8s.io/docs/start/)
- [kubectl](https://kubernetes.io/docs/tasks/tools/)
- [Docker](https://docs.docker.com/get-docker/)
- [Helm](https://helm.sh/docs/intro/install/)（Helm 部署章節需要）

---

## 觀察叢集初始狀態

啟動 Minikube 後，確認節點與 namespace：

```bash
kubectl get node
```

只有一個 node，同時也是 control-plane。

```bash
kubectl get namespaces
```

Minikube 預設建立四個 namespace。若不指定 `-n`，指令會作用在 `default` namespace。

```bash
kubectl get pod -A
```

`-A` 列出所有 namespace 的 Pod（包括 `kube-system` 下的系統 Pod）。

```bash
kubectl get services -A
```

查看系統建立的 Service。

---

## 建立 Docker 映像

`app/src/index.php` 會從環境變數讀取 `MESSAGE`、`USERNAME`、`PASSWORD`，並支援 `?url=` 參數在 Pod 內部發起 HTTP 請求。

```bash
# 在本機建立並測試
docker build -t kk/k8s-demo-app .
docker container run -p 8081:80 -e MESSAGE=Hello -e USERNAME=KK -e PASSWORD=password kk/k8s-demo-app
```

本專案的 YAML 已指向 `vup4k0806/k8s-demo-app:latest`（公開映像），直接 apply 即可，不需要自己 push。

若有修改 `index.php` 想部署自己的版本，才需要以下步驟。`docker tag` 不是改名，而是為同一份映像建立新標籤（alias）。Docker Hub 要求映像名稱前綴必須是你的帳號，tag 的目的就是讓映像符合這個格式：

```bash
docker login
docker tag kk/k8s-demo-app <your-dockerhub-username>/k8s-demo-app
docker push <your-dockerhub-username>/k8s-demo-app
```

push 後，再把 `resources/` 與 `helm_chart/templates/` 裡的 `image:` 欄位改成你的帳號名稱。

---

## 部署流程（原始 YAML）

### 1. 建立 namespace

```bash
kubectl create namespace ns1
kubectl create namespace ns2
```

### 2. 建立 ConfigMap

`cm1.yaml` 存放非敏感設定，以 `-n ns1` 指定部署到 ns1：

```bash
kubectl apply -f resources/cm1.yaml -n ns1
kubectl get configmap -n ns1
```

### 3. 建立 Secret

Secret 的值需先 base64 編碼：

```bash
echo -n "passw0rd" | base64
# 輸出：cGFzc3cwcmQK
```

```bash
kubectl apply -f resources/secret1.yaml -n ns1
```

### 4. 建立 Pod

`po1.yaml` 從 ConfigMap 取得 `USERNAME`，從 Secret 取得 `PASSWORD`：

```bash
kubectl apply -f resources/po1.yaml -n ns1
kubectl get pods -n ns1
```



### 5. 建立 Service

```bash
kubectl apply -f resources/po1-svc.yaml -n ns1
kubectl get services -n ns1
```

Service 以 selector 綁定 Pod，讓其他資源可透過固定名稱存取。

### 6. 建立 Deployment

Deployment 管理多個相同的 Pod 副本（replicas: 3）：

```bash
kubectl apply -f resources/deploy1.yaml -n ns1
kubectl get deployments -n ns1
kubectl get pods -n ns1
```

`kubectl get pods` 會看到多了三個由 Deployment 產生的 Pod。

```bash
kubectl apply -f resources/deploy1-svc.yaml -n ns1
kubectl get services -n ns1
```

本機可用 port-forward 驗證：

```bash
kubectl port-forward services/deploy1-svc 8082:80 -n ns1
```

### 7. 以相同步驟建立 ns2 的資源

將 `cm2.yaml`、`secret2.yaml`、`po2.yaml`、`po2-svc.yaml`、`deploy2.yaml`、`deploy2-svc.yaml` 分別 apply 至 ns2。

---

## Ingress 設定

### 啟用 Ingress addon

```bash
minikube addons enable ingress
kubectl get pods -n ingress-nginx
```

確認 ingress-nginx controller Pod 已啟動。

### 套用 Ingress 路由規則

```bash
kubectl apply -f resources/ing1.yaml -n ns1
kubectl apply -f resources/ing2.yaml -n ns2
kubectl get ingress -n ns1
kubectl get ingress -n ns2
```

兩個 namespace 各自定義 Ingress，使用相同 host `foo.bar.com`，以不同路徑分流：

| Namespace | 路徑       | 後端 Service |
|-----------|------------|--------------|
| ns1       | `/po1`     | po1-svc      |
| ns1       | `/deploy1` | deploy1-svc  |
| ns2       | `/po2`     | po2-svc      |
| ns2       | `/deploy2` | deploy2-svc  |

### 設定本機 hosts

Minikube 在 macOS 上跑在 Docker 內，Ingress 的外部 IP 是 `127.0.0.1`，需搭配 `minikube tunnel` 才能從本機存取：

```bash
# /etc/hosts 加入以下一行
127.0.0.1  foo.bar.com
```

```bash
# 開啟 tunnel（維持執行中，另開一個終端機視窗）
minikube tunnel
```

`minikube tunnel` 會建立網路通道，讓 Ingress Controller 可以從本機接收流量。

---

## 同 namespace 內互通

在同一個 namespace 下，Service 之間可直接用 Service 名稱互通，不需要額外設定。

`index.php` 接受 `?url=` 參數，從 Pod 內部發起 HTTP 請求並顯示回應：

```bash
# deploy1 的 Pod 在內部呼叫 po1-svc（同在 ns1）
http://foo.bar.com/deploy1?url=http://po1-svc
```

流程：

```
你的電腦
  │
  ▼ (minikube tunnel)
Ingress → deploy1-svc → deploy1 (某個 Pod)
                          │
                          ▼ (K8s 內網 DNS，直接以 Service 名稱存取)
                        po1-svc → po1
                          │
                          ▼ 回傳結果
```

---

## 跨 namespace 互通

跨 namespace 呼叫時，需使用完整的 K8s DNS 名稱：

```
<service-name>.<namespace>.svc.cluster.local
```

範例：讓 ns2 的 po2 呼叫 ns1 的 po1：

```bash
http://foo.bar.com/po2?url=http://po1-svc.ns1.svc.cluster.local
```

流程：

```
你的電腦
  │
  ▼ http://foo.bar.com/po2?url=...
Ingress → po2-svc (ns2) → po2 (Pod)
                              │
                              ▼ PHP file_get_contents 發起請求
                          po1-svc.ns1.svc.cluster.local
                              │
                              ▼
                           po1 (ns1)
                              │
                              ▼ 回傳結果
```

回應中第一段 `Server` IP 是 po2 的，`Response from ...` 以下的 `Server` IP 是 po1 的，驗證了跨 namespace 的 Service 可以互通。

---

## 架構總覽

```
外部請求 (foo.bar.com)
    │
    ▼ minikube tunnel → 127.0.0.1
 Ingress (NGINX)
    │
    ├── /po1     (ns1) → po1-svc     → po1
    ├── /deploy1 (ns1) → deploy1-svc → deploy1 (x3 replicas)
    ├── /po2     (ns2) → po2-svc     → po2
    └── /deploy2 (ns2) → deploy2-svc → deploy2

同 namespace 內網：
  po1-svc ←→ deploy1-svc   (直接用 Service 名稱)

跨 namespace 內網：
  po2 → po1-svc.ns1.svc.cluster.local → po1
```

---

## 以 Helm 部署

Helm Chart 將資源模板化，透過 `values.yaml` 中的 `nsId` 切換 namespace，一份 chart 部署兩套環境。

預覽產生的 YAML（不實際部署）：

```bash
helm template helm_chart/ | more
helm template helm_chart/ --values=helm_chart/values-ns2.yaml | more

# 或直接覆寫變數
helm template helm_chart/ --set nsId=2 | more
```

清除舊資源後，以 Helm 安裝：

```bash
helm install chart1 helm_chart/ -n ns1
kubectl get all -n ns1

helm install chart2 helm_chart/ -n ns2 --values=helm_chart/values-ns2.yaml
kubectl get all -n ns2
```

![helm install ns1](static/imgs/screenshot_for_createns1.png)
![helm install ns2](static/imgs/screenshot_for_createns2.png)



驗證跨 namespace 互通：

```bash
http://foo.bar.com/po1?url=http://po2-svc.ns2.svc.cluster.local
```

---

## 截圖

![跨 namespace Ingress 示範](static/imgs/screenshopt_for_k8slab.png)

---

