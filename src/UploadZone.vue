<script setup lang="ts">

import { ref, defineProps, withDefaults, defineEmits, getCurrentInstance, defineExpose } from 'vue'

import $ from 'jQuery'

interface Props {
  target: string
  tag?: string
  maxJobs?: number
  autoStart?: boolean | string
  autoReset?: boolean | string
  keepGoing?: boolean | string
}

const props = withDefaults(defineProps<Props>(), {
  maxJobs: 3,
  autoStart: false,
  autoReset: false,
  keepGoing: false,
  tag: 'div'
})

const emit = defineEmits<{
  (e: 'drag:over', evt: any): void
  (e: 'drag:out', evt: any): void
  (e: 'drop', evt: any, files: any[]): void
  (e: 'upload:progress', vec: any[]): void
  (e: 'upload:done', vec: any[]): void
}>()

const inst = getCurrentInstance()

enum Mode {
  m_idle = 'idle',
  m_hover = 'hover',
  m_wait = 'wait',
  m_uploading = 'uploading',
  m_done = 'done',
  m_error = 'error',
  m_with_errors = 'with_errors', // XXX av when keep-going set
  m_aborted = 'aborted'
}

const mode = ref('idle' as Mode)

const queue = ref([] as any[])
const progress = ref({} as any)
const results = ref([] as any[])
const current = ref([] as any[])
const error = ref({} as any)

const errors = ref([] as any)

let files: any[] = []

let cur_jobs = 0
let cur_file = 0

let aborted = false

const extract_files = (evt: any): any[] => {
  const files = []

  if(evt.dataTransfer && evt.dataTransfer.items !== undefined) {
    for(const item of evt.dataTransfer.items)
      if(item.kind == 'file')
        files.push(item.getAsFile())
  }
  else
    if(evt.dataTransfer)
      for(const file of evt.dataTransfer.files)
        files.push(file)
    else // XXX elem click
      for(const file of evt.target.files)
        files.push(file)

  return files
}

const drop = (evt: any): void => {
  if(mode.value != Mode.m_hover && mode.value != Mode.m_idle && mode.value != Mode.m_wait)
    return

  mode.value = Mode.m_wait
  files = files.concat(extract_files(evt))
  emit('drop', evt, files)
  queue.value = files

  if(props.autoStart || props.autoStart === '')
    start_cb()
}

const drag_over = (evt: any): void => {
  if(mode.value != Mode.m_idle)
    return

  mode.value = Mode.m_hover
  emit('drag:over', evt)
}

const drag_out = (evt: any): void => {
  if(mode.value != Mode.m_hover)
    return

  mode.value = Mode.m_idle
  //console.log('out', evt)
  emit('drag:out', evt)
}

const start_cb = (): void => {
  if(mode.value != Mode.m_wait)
    return

  aborted = false

  mode.value = Mode.m_uploading

  results.value = []
  progress.value = []
  current.value = []
  errors.value = []

  error.value = {}
  cur_file = 0
  cur_jobs = 0

  while(cur_jobs < props.maxJobs && cur_file < files.length)
    start_job()
}

const reset_cb = (): void => {
  mode.value = Mode.m_idle
  files = []
}

const pick_cb = (): void => {
  if(mode.value != Mode.m_idle && Mode.value != Mode.m_wait)
    return

  const inp = document.createElement('input')
  inp.setAttribute('type', 'file')
  inp.setAttribute('multiple', 'true')
  inp.addEventListener('input', (evt: any): void => {
    drop(evt)
  })
  inp.click()
}

const abort_cb = (): void => {
  if(mode.value != Mode.m_uploading)
    return

  aborted = true
}

const start_job = () => {
  cur_jobs += 1

  const fd = new FormData()
  const cur = files[cur_file]
  fd.append('file', files[cur_file])

  current.value.push({
    file: files[cur_file],
    current: 0,
    total: files[cur_file].size
  })

  queue.value = queue.value.filter(queue.value, (file) => {
    return file !== cur
  })

  cur_file += 1

  $.ajax({
    url: props.target,
    type: 'POST',
    data: fd,
    contentType: false,
    processData: false,
    xhr: (): any => {
      const xhr = new window.XMLHttpRequest()

      xhr.upload.addEventListener('progress', (evt): void => {
        queue.value.forEach(current.value, (item) => {
          if(item.file === cur) {
            item.current = evt.loaded
            item.total = evt.total
          }
        })
      })

      return xhr
    }
  })
    .then((res: any) => {
      current.value = queue.value.filter(current.value, (item) => {
        return item.file !== cur
      })

      progress.value.push(res)
      results.value.push(res)

      emit('upload:progress', progress.value)

      cur_jobs -= 1

      if(aborted) {
        if(props.autoReset || props.autoReset === '') {
          mode.value = Mode.m_idle
          files = [] // # XXX can drop w/o reset
        }
        else
          mode.value = Mode.m_aborted

        return
      }

      if(cur_file == files.length && !cur_jobs) {
        emit('upload:done', results.value)

        files = [] // XXX can drop w/o reset

        if(errors.value.length) {
          if(props.keepGoing || props.keepGoing === '')
            mode.value = Mode.m_with_errors
          else
            mode.value = Mode.m_error
        }
        else
          if(props.autoReset || props.autoReset === '')
            mode.value = Mode.m_idle
          else
            mode.value = Mode.m_done

        return
      }

      while(cur_jobs < props.maxJobs && cur_file < files.length)
        start_job()
    })
    .catch((err: any) => {
      if(props.keepGoing || props.keepGoing === '') {
        current.value = current.value.filter(current.value, (item) => {
          return item.file !== cur
        })

        errors.value.push({
          error: err,
          file: cur
        })

        cur_jobs -= 1

        if(cur_file == files.length && !cur_jobs) {
          mode.value = Mode.m_with_errors
          return
        }

        while(cur_jobs < props.maxJobs && cur_file < files.length)
          start_job()
      }
      else {
        mode.value = Mode.m_error
        error.value = err
      }
    })
}

defineExpose({
  reset: reset_cb,
  start: start_cb,
  pick: pick_cb,
  abort: abort_cb
})

</script>

<template bindings="">
  <component is='props.tag' @drop.prevent='drop' @dragover.prevent='drag_over' @dragleave.prevent='drag_out'>
    <slot name='idle' v-if='mode == "idle"' v-bind:pick='pick_cb' />
    <slot name='hover' v-if='mode == "hover"' />
    <slot name='wait' v-if='mode == "wait"' v-bind:start='start_cb'
          v-bind:queue='queue' v-bind:reset='reset_cb' />
    <slot name='uploading' v-if='mode == "uploading"'
          v-bind:progress='progress'
          v-bind:current='current'
          v-bind:queue='queue'
          v-bind:errors='errors'
          v-bind:abort='abort_cb' />
    <slot name='done' v-if='mode == "done"' v-bind:result='results' v-bind:reset='reset_cb' />
    <slot name='error' v-if='mode == "error"' v-bind:error='error' v-bind:reset='reset_cb' />
    <slot name='aborted' v-if='mode == "aborted"' v-bind:result='results' v-bind:reset='reset_cb' />
    <slot name='with-errors' v-if='mode == "with_errors"' v-bind:result='results' v-bind:errors='errors'
          v-bind:reset='reset_cb' />
  </component>
</template>

