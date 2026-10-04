import { csrfToken } from '@/lib/csrf';
import { t } from '@/lib/i18n';

/**
 * رفعُ ملفّ الدرس أجزاءً — المواصفة §5-أ-4-ب.
 *
 * **ملفّ نصف غيغابايت لا يُرسَل في طلبٍ واحد.** على اتّصالٍ ضعيف يأخذ دقائق،
 * وانقطاعُ ثانيةٍ في آخره يُعيده من الصفر. فيُقطَّع هنا أجزاءً بالحجم الذي
 * يقوله الخادم، ويُرفع جزءاً بعد جزء:
 *   - **الجزءُ الساقط يُعاد وحده**، بتراجعٍ متزايد، وينتظر عودة الشبكة إن انقطعت.
 *   - إن نفدت المحاولات **وقف الرفع ولم يضع**: يُستأنف من الأجزاء الناقصة وحدها.
 *   - ثمّ يُطلب الجمعُ والفحص، فيُعرف عيبُ الملفّ قبل ملء بقيّة النموذج.
 *
 * والخادم يكنس ما لم يكتمل، والشاشةُ تطلب حذفه متى أُلغي — فلا يبقى على
 * القرص رفعٌ بلا صاحب.
 */

export interface UploadInfo {
  id: string;
  name: string;
  status: 'receiving' | 'ready';
  size: number;
  chunk_bytes: number;
  chunk_count: number;
  received: number[];
  duration_seconds: number | null;
}

/** رفضٌ برسالةٍ تُعرض كما هي. و`resumable`: ما رُفع محفوظ، فيُستأنف ولا يُعاد. */
export class UploadError extends Error {
  constructor(
    message: string,
    readonly resumable: boolean,
  ) {
    super(message);
  }
}

interface Options {
  signal: AbortSignal;
  /** يُعرف المعرّف فور البدء — ليُحذف الرفع إن أُلغي قبل اكتماله. */
  onStarted: (id: string) => void;
  onProgress: (sent: number, total: number) => void;
  onChecking: () => void;
}

/** محاولاتُ الجزء الواحد قبل أن يقف الرفع ويُعرض «أكمِل». */
const ATTEMPTS = 6;

const BASE = '/panel/uploads';

/**
 * يرفع الملفّ كلّه، أو يُكمل رفعاً بدأ (`resumeId`)، ويعيد ما قاله الخادم بعد الفحص.
 *
 * @throws UploadError
 */
export async function uploadInChunks(file: File, options: Options, resumeId?: string): Promise<UploadInfo> {
  const info = resumeId !== undefined
    ? await json<UploadInfo>('GET', `${BASE}/${resumeId}`, undefined, options.signal)
    : await json<UploadInfo>('POST', BASE, { name: file.name, size: file.size }, options.signal);

  options.onStarted(info.id);

  // جولتان على الأكثر: إن قال الجمعُ إنّ جزءاً لم يصل، يُرفع الناقص مرّةً ثانية.
  for (let round = 0; round < 2; round += 1) {
    const status = round === 0 ? info : await json<UploadInfo>('GET', `${BASE}/${info.id}`, undefined, options.signal);

    if (status.status === 'ready') {
      return status;
    }

    await sendMissing(file, status, options);
    options.onChecking();

    try {
      return await json<UploadInfo>('POST', `${BASE}/${info.id}/complete`, undefined, options.signal);
    } catch (error) {
      if (!(error instanceof IncompleteUpload)) {
        throw error;
      }
    }
  }

  throw new UploadError(t('lectures.create.source.upload_paused'), true);
}

/** يُلغى من الشاشة — وبـ`keepalive` حين تُغادَر الصفحة، فيصل الطلب ولو أُغلقت. */
export function discardUpload(id: string, keepalive = false): void {
  void fetch(`${BASE}/${id}`, {
    method: 'DELETE',
    keepalive,
    headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
  }).catch(() => undefined);
}

async function sendMissing(file: File, info: UploadInfo, options: Options): Promise<void> {
  const received = new Set(info.received);
  let sent = 0;

  for (const index of received) {
    sent += chunkOf(file, info, index).size;
  }

  options.onProgress(sent, file.size);

  for (let index = 0; index < info.chunk_count; index += 1) {
    if (received.has(index)) {
      continue;
    }

    const chunk = chunkOf(file, info, index);

    await putWithRetry(`${BASE}/${info.id}/chunks/${index}`, chunk, options, (loaded) => {
      options.onProgress(sent + loaded, file.size);
    });

    sent += chunk.size;
    options.onProgress(sent, file.size);
  }
}

function chunkOf(file: File, info: UploadInfo, index: number): Blob {
  return file.slice(index * info.chunk_bytes, Math.min(file.size, (index + 1) * info.chunk_bytes));
}

/**
 * جزءٌ واحد، يُعاد عند الإخفاق بتراجع ١ ثمّ ٢ ثمّ ٤… ثوانٍ، وينتظر عودة الشبكة.
 *
 * @throws UploadError إن نفدت المحاولات (فيُستأنف لاحقاً) أو رُفض رفضاً لا يُصلحه التكرار.
 */
async function putWithRetry(url: string, chunk: Blob, options: Options, onLoaded: (loaded: number) => void): Promise<void> {
  for (let attempt = 1; attempt <= ATTEMPTS; attempt += 1) {
    await waitForNetwork(options.signal);

    const status = await put(url, chunk, options.signal, onLoaded);

    if (status >= 200 && status < 300) {
      return;
    }

    // انتهت الجلسة أو كُنس الرفع أو أُغلق: التكرارُ لا يُصلح شيئاً من هذا.
    if (status === 419) {
      throw new UploadError(t('lectures.create.preflight.session_expired'), false);
    }

    if (status === 404 || status === 409) {
      throw new UploadError(t('lectures.create.source.upload_expired'), false);
    }

    if (attempt < ATTEMPTS) {
      await sleep(1000 * 2 ** (attempt - 1), options.signal);
    }
  }

  throw new UploadError(t('lectures.create.source.upload_paused'), true);
}

/** ‏`XMLHttpRequest` لا `fetch`: وحده يُخبر بتقدّم الرفع داخل الجزء. ويعيد 0 لانقطاع الشبكة. */
function put(url: string, chunk: Blob, signal: AbortSignal, onLoaded: (loaded: number) => void): Promise<number> {
  return new Promise((resolve, reject) => {
    if (signal.aborted) {
      reject(new DOMException('aborted', 'AbortError'));
      return;
    }

    const request = new XMLHttpRequest();

    request.open('PUT', url);
    request.setRequestHeader('Content-Type', 'application/octet-stream');
    request.setRequestHeader('X-CSRF-TOKEN', csrfToken());
    request.setRequestHeader('Accept', 'application/json');

    request.upload.onprogress = (event) => onLoaded(event.loaded);
    request.onload = () => resolve(request.status);
    request.onerror = () => resolve(0);
    request.ontimeout = () => resolve(0);
    request.onabort = () => reject(new DOMException('aborted', 'AbortError'));

    signal.addEventListener('abort', () => request.abort(), { once: true });

    request.send(chunk);
  });
}

class IncompleteUpload extends Error {}

/** @throws UploadError */
async function json<T>(method: string, url: string, body: unknown, signal: AbortSignal): Promise<T> {
  let response: Response;

  try {
    response = await fetch(url, {
      method,
      signal,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        Accept: 'application/json',
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch (error) {
    if (signal.aborted) {
      throw error;
    }

    // بدءٌ أو جمعٌ سقط بالشبكة: ما رُفع محفوظ على الخادم.
    throw new UploadError(t('lectures.create.source.upload_paused'), true);
  }

  if (response.ok) {
    return (await response.json()) as T;
  }

  const payload = (await response.json().catch(() => ({}))) as { message?: string; reason?: string };

  if (response.status === 409 && payload.reason === 'upload_incomplete') {
    throw new IncompleteUpload();
  }

  if (response.status === 419) {
    throw new UploadError(t('lectures.create.preflight.session_expired'), false);
  }

  // كُنس الرفع أو حُذف — لا شيء يُستأنف.
  if (response.status === 404) {
    throw new UploadError(t('lectures.create.source.upload_expired'), false);
  }

  // عطلٌ عند الخادم يُستأنف بعده؛ ورفضٌ برسالة (حجم، نوع، صوت، مدّة، حصّة) لا يُستأنف.
  throw new UploadError(payload.message ?? t('lectures.create.source.upload_failed'), response.status >= 500);
}

function waitForNetwork(signal: AbortSignal): Promise<void> {
  if (navigator.onLine) {
    return Promise.resolve();
  }

  return new Promise((resolve, reject) => {
    const online = () => {
      signal.removeEventListener('abort', abort);
      resolve();
    };
    const abort = () => {
      window.removeEventListener('online', online);
      reject(new DOMException('aborted', 'AbortError'));
    };

    window.addEventListener('online', online, { once: true });
    signal.addEventListener('abort', abort, { once: true });
  });
}

function sleep(ms: number, signal: AbortSignal): Promise<void> {
  return new Promise((resolve, reject) => {
    const timer = window.setTimeout(resolve, ms);

    signal.addEventListener('abort', () => {
      window.clearTimeout(timer);
      reject(new DOMException('aborted', 'AbortError'));
    }, { once: true });
  });
}
