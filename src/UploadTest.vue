<script setup lang="ts">

import { ref, getCurrentInstance } from 'vue'

import UploadZone from './UploadZone.vue'

const inst = getCurrentInstance()

const auto_start = ref(false)
const auto_reset = ref(false)
const keep_going = ref(false)
const max_jobs = ref(3)

const reset = (): void => {
  (inst as any).refs.uploader.reset()
}

const start = (): void => {
  (inst as any).refs.uploader.start()
}

</script>

<template>
  <component class='uploader' :is='UploadZone' target='https://vite.js29a.usermd.net/upload.php'
             :max-jobs='max_jobs' :auto-start='auto_start' :auto-reset='auto_reset'
             :keep-going='keep_going' ref='uploader'>
    <template #idle='{ pick }'>
      <div class='idle'>
        idle
        <br />
        <button @click='pick()'>pick</button>
      </div>
    </template>

    <template #hover>
      <div class='hover'>
        hover
      </div>
    </template>

    <template #wait='{ start, queue }'>
      <div class='wait'>
        wait
        <br />
        <button @click='pick()'>pick</button>
        <br />
        <button @click='start()'>start</button>
        <br/>
        <div v-for='file in queue'>{{ file.name }}</div>
      </div>
    </template>

    <template #uploading='{ progress, current, queue, abort, errors }'>
      <div class='uploading'>
        uploading
        <br />
        <button @click='abort()'>abort</button>
        <br />
        {{ progress.length }} / {{ current.length }} / {{ queue.length }}
        <br />
        <button @click='abort()'>abort</button>
        <b>errors:</b> <span v-for='error in errors'>{{ error.file.name }}</span>
        <b>progress:</b> <div v-for='item in progress'>{{ item }}</div>
        <b>current:</b>
        <div v-for='item in current'>
          {{ item.file.name }}: {{ item.current }} of {{ item.total }}
        </div>
        <b>remaining:</b>
        <div v-for='file in queue'>{{ file.name }}</div>
      </div>
    </template>

    <template #done='{ result, reset }'>
      <div class='done'>
        done
        <br />
        <button @click='reset()'>reset</button>
        <br />
        <div v-for='item in result'>{{ item }}</div>
      </div>
    </template>

    <template #error='{ error, reset }'>
      <div class='error'>
        error
        <br />
        <button @click='reset()'>reset</button>
        <div>{{ error }}</div>
      </div>
    </template>

    <template #aborted='{ result, reset }'>
      <div class='aborted'>
        aborted
        <br />
        <button @click='reset()'>reset</button>
        <div v-for='item in result'>{{ item }}</div>
      </div>
    </template>

    <template #with-errors='{ result, errors }'>
      <div class='with-errors'>
        with errors
        <br />
        <b>errors(s):</b>
        <div v-for='item in errors'>{{ item.file.name }} / {{ item.error }}</div>
        <b>ok:</b>
        <div v-for='item in result'>{{ item }}</div>
      </div>
    </template>
  </component>
</template>

<style scoped>

.uploader {
  border: 3px dashed #fff;
  width: 512px;
  height: 384px;
  position: absolute;
  border-radius: 20px;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

.idle,
.hover,
.wait,
.uploading,
.done,
.error,
.aborted,
.with-errors {
  width: 100%;
  height: 100%;
  border: 1px solid #0ff;
  padding: 5px;
  overflow: auto;
}

.status {
  width: 25%;
  height: auto;
  background-color: #555;
  color: #fff;
  position: absolute;
  right: 0px;
  bottom: 0px;
  padding: 2px 2px 2px 2px;
  text-align: center;
  font-weight: bold;
  border-radius: 10px 0px 0px 0px;
  border: 1px solid #0ff;
  opacity: 0.75;
}

.idle {
  background-color: #088;
  color: black;
}

.hover {
  background-color: #088;
  color: white;
  cursor: copy;
}

.wait {
  background-color: #880;
  color: black;
}

.uploading {
  background-color: #050;
  color: white;
}

.done {
  background-color: #00f;
  color: white;
}

.error {
  background-color: #800;
  color: white;
}

.aborted {
  background-color: #300;
  color: white;
}

.with-errors {
  background-color: #800;
  color: white;
}

</style>

