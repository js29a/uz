<script setup lang="ts">

import { ref, computed, defineProps, withDefaults, defineEmits, getCurrentInstance, defineExpose, watch, nextTick } from 'vue'

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

enum State {
  s_idle = 'idle',
  s_hover = 'hover',
  s_wait = 'wait',
  s_uploading = 'uploading',
  s_done = 'done',
  s_error = 'error',
  s_with_errors = 'with_errors',
  s_aborted = 'aborted'
}

const emit = defineEmits<{
  (e: 'update:canReset', flag: boolean): void
  (e: 'update:canPick', flag: boolean): void
  (e: 'update:canStart', flag: boolean): void
  (e: 'update:canAbort', flag: boolean): void
  (e: 'update:state', new_state: State): void

  (e: 'files:add', evt: any, files: any[]): void
  (e: 'files:clear'): void

  (e: 'drag:over', evt: any): void
  (e: 'drag:out', evt: any): void

  (e: 'upload:start', queue: any[]): void
  (e: 'upload:progress', progress: any[], current: any[], queue: any[]): void

  (e: 'upload:done', result: any[], errors: any[]): void
  (e: 'upload:error', error: any): void
  (e: 'upload:aborted', progress: any[], current: any[], queue: any[], errors: any[]): void

  (e: 'debug', ... args: any[]): void
}>()

const do_emit = (code: string, ... args: any[]) => {
  // @ts-ignore
  emit.apply(null, [code].concat(args))
  emit.apply(null, ['debug', code, args])
}

const inst = getCurrentInstance()

const state = ref(State.s_idle)

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
    else
      for(const file of evt.target.files)
        files.push(file)

  return files
}

const drop = (evt: any): void => {
  if(state.value != State.s_hover && state.value != State.s_idle && state.value != State.s_wait)
    return

  state.value = State.s_wait
  files = files.concat(extract_files(evt))
  do_emit('files:add', [evt, files])
  queue.value = files

  if(props.autoStart || props.autoStart === '')
    start_cb()
}

const drag_over = (evt: any): void => {
  if(state.value != State.s_idle)
    return

  state.value = State.s_hover

  do_emit('drag:over', evt)
}

const drag_out = (evt: any): void => {
  if(state.value != State.s_hover)
    return

  state.value = State.s_idle

  do_emit('drag:out', evt)
}

const start_cb = (): void => {
  if(state.value != State.s_wait)
    return

  do_emit('upload:start', [files])

  aborted = false

  state.value = State.s_uploading

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
  if(state.value == State.s_idle)
    return

  state.value = State.s_idle
  files = []
  do_emit('files:clear', [])
}

const pick_cb = (): void => {
  if(state.value != State.s_idle && state.value != State.s_wait)
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
  if(state.value != State.s_uploading)
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

  queue.value = queue.value.filter((file) => {
    return file !== cur
  })

  do_emit('upload:progress', progress.value, current.value, queue.value)

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
        current.value.forEach((item) => {
          if(item.file === cur) {
            item.current = evt.loaded
            item.total = evt.total
          }
        })
        do_emit('upload:progress', progress.value, current.value, queue.value)
      })

      return xhr
    }
  })
    .then((res: any) => {
      current.value = current.value.filter((item) => {
        return item.file !== cur
      })

      do_emit('upload:progress', progress.value, current.value, queue.value)

      progress.value.push(res)
      results.value.push(res)

      cur_jobs -= 1

      if(aborted) {
        if(props.autoReset || props.autoReset === '') {
          state.value = State.s_idle
          files = []
          do_emit('files:clear', [])
        }
        else
          if(state.value != State.s_aborted) {
            state.value = State.s_aborted
            do_emit('upload:aborted', progress.value, current.value, queue.value, errors.value)
          }

        return
      }

      if(cur_file == files.length && !cur_jobs) {
        files = []
        do_emit('files:clear', [])

        if(errors.value.length)
          if(props.keepGoing || props.keepGoing === '') {
            state.value = State.s_with_errors
            do_emit('upload:done', results.value, errors.value)
          }
          else
            state.value = State.s_error
        else
          if(props.autoReset || props.autoReset === '')
            state.value = State.s_idle
          else {
            state.value = State.s_done
            do_emit('upload:done', results.value, errors.value)
          }

        return
      }

      while(cur_jobs < props.maxJobs && cur_file < files.length)
        start_job()
    })
    .catch((err: any) => {
      if(props.keepGoing || props.keepGoing === '') {
        current.value = current.value.filter((item) => {
          return item.file !== cur
        })

        errors.value.push({
          error: err,
          file: cur
        })

        do_emit('upload:error', err)

        cur_jobs -= 1

        if(cur_file == files.length && !cur_jobs) {
          state.value = State.s_with_errors
          do_emit('upload:done', results.value, errors.value)
          return
        }

        while(cur_jobs < props.maxJobs && cur_file < files.length)
          start_job()
      }
      else {
        state.value = State.s_error
        error.value = err
        do_emit('upload:error', err)
      }
    })
}

const can_pick = computed({
  get: () => {
    return state.value == State.s_idle || state.value == State.s_wait
  },
  set: () => {
  }
})

const can_start = computed({
  get: () => {
    return state.value == State.s_wait
  },
  set: () => {
  }
})

const can_abort = computed({
  get: () => {
    return state.value == State.s_uploading
  },
  set: () => {
  }
})

const can_reset = computed({
  get: () => {
    return state.value != State.s_idle
  },
  set: () => {
  }
})

const on_state_changed = (): void => {
  do_emit('update:canPick', can_pick.value)
  do_emit('update:canStart', can_start.value)
  do_emit('update:canAbort', can_abort.value)
  do_emit('update:canReset', can_reset.value)

  do_emit('update:state', state.value)
}

watch(() => { return state.value }, (t: State, f: State) => { nextTick(on_state_changed) } )

nextTick(on_state_changed)

defineExpose({
  reset: reset_cb,
  start: start_cb,
  pick: pick_cb,
  abort: abort_cb,

  can_pick,
  can_start,
  can_abort,
  can_reset
})

</script>

<template bindings="">
  <component is='props.tag' @drop.prevent='drop' @dragover.prevent='drag_over' @dragleave.prevent='drag_out'>
    <slot name='idle' v-if='state == "idle"' v-bind:pick='pick_cb' />
    <slot name='hover' v-if='state == "hover"' />
    <slot name='wait' v-if='state == "wait"' v-bind:start='start_cb'
          v-bind:queue='queue' v-bind:reset='reset_cb' />
    <slot name='uploading' v-if='state == "uploading"'
          v-bind:progress='progress'
          v-bind:current='current'
          v-bind:queue='queue'
          v-bind:errors='errors'
          v-bind:abort='abort_cb' />
    <slot name='done' v-if='state == "done"' v-bind:result='results' v-bind:reset='reset_cb' />
    <slot name='error' v-if='state == "error"' v-bind:error='error' v-bind:reset='reset_cb' />
    <slot name='aborted' v-if='state == "aborted"' v-bind:result='results' v-bind:reset='reset_cb' />
    <slot name='with-errors' v-if='state == "with_errors"' v-bind:result='results' v-bind:errors='errors'
          v-bind:reset='reset_cb' />
  </component>
</template>

