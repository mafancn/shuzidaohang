import request from '@/utils/http'
import {
  qifuCsrf,
  qifuRestoreBackup,
  qifuSiteMeta,
  qifuUpload,
  type QifuBackupRestoreResult,
  type QifuSiteMeta,
  type QifuUpdateResult
} from './qifu'

let csrfToken = ''
let csrfRequest: Promise<string> | null = null

export function setCsrfToken(token: string) {
  csrfToken = String(token || '')
}

export async function getCsrfToken(force = false) {
  if (!force && csrfToken) return csrfToken
  if (!csrfRequest) {
    csrfRequest = qifuCsrf()
      .then((token) => {
        setCsrfToken(token)
        return token
      })
      .finally(() => {
        csrfRequest = null
      })
  }
  return csrfRequest
}

export async function qifuActionOptimized<T = unknown>(
  action: string,
  params: Record<string, any> = {}
) {
  const token = await getCsrfToken()
  return request.post<T>({
    url: `./api.php?action=${encodeURIComponent(action)}`,
    headers: { 'X-CSRF-Token': token },
    params
  })
}

export async function qifuUploadOptimized(
  file: File,
  slot: string,
  position: string
) {
  const token = await getCsrfToken()
  return qifuUpload(file, slot, position, token)
}

export async function qifuUploadAvatarOptimized(file: File) {
  const token = await getCsrfToken()
  const form = new FormData()
  form.append('file', file)
  const response = await fetch('./api.php?action=profile_avatar_upload', {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-CSRF-Token': token },
    body: form
  })
  const json = await response.json().catch(() => null)
  if (!json || json.code !== 200) throw new Error(json?.msg || '头像上传失败')
  return json.data
}

export async function qifuSiteMetaOptimized(url: string): Promise<QifuSiteMeta> {
  return qifuSiteMeta(url)
}

export async function qifuRestoreBackupOptimized(
  file: File,
  password: string
): Promise<QifuBackupRestoreResult> {
  return qifuRestoreBackup(file, password)
}

export async function qifuApplyUpdateOptimized(operationId: string) {
  const token = await getCsrfToken()
  return request.post<QifuUpdateResult>({
    url: './api.php?action=update_apply',
    headers: { 'X-CSRF-Token': token },
    params: { operationId },
    timeout: 300000
  })
}
