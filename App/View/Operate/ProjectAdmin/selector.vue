<style scoped>
  .el-form-item--mini.el-form-item, .el-form-item--small.el-form-item {
    margin: 10px 0;
  }
</style>
<template>
  <el-form ref="dataForm" :rules="rules" :model="info" label-width="0">
  <el-form-item label="" prop="uuid">
  <el-select style="width: 90%;"
             filterable
             remote
             v-model="info.uuid"
             reserve-keyword
             placeholder="输入关键词可搜索"
             :loading="loading"
             :remote-method="correlationList"
             @click.native__="correlationList">
    <el-option
            v-for="item in correlation_list"
            :key="item.uuid"
            :label="item.title|ellipsis_and_enabled_text(22, item.is_enabled)"
            :value="item.uuid">
      <span style="float: left">{{item.title|ellipsis(22)}}</span>
      <span style="float: right; color: #8492a6; font-size: 13px">{{item.is_enabled|is_enabled_text}}</span>
    </el-option>
  </el-select>
  </el-form-item>
  </el-form>
</template>
<script>
  module.exports = {
    props: ['info'],
    data() {
      return {
        tableRowClassName({row, rowIndex}) {
          return 'row_id_' + row.uuid;
        },
        correlation_list: this.info.uuid ? [this.info] : [],
        full_correlation_list: [],
        picture:'',
        country: [],
        state: [],
        rules: {
          uuid: [{ required: true, message: '请选择一个项目', trigger: 'change' }]
        },
        editor_title: '',
        is_show_editor:false,
        limit:7,
        default_query:{},
        query:{},
        total: 0,
        list: [],
        loading: false,
        empty_list:'',
        default_info:{
          is_enabled:true
        },
        //info: {},
        posting:false
      }
    },
    filters: {
      ellipsis_and_enabled_text(value, length, is_enabled, text){
        if (!value) return ''
        if (value.length > length) {
          value = value.slice(0, length) + '...'
        }
        let enabled_text=text?text:'[未上线]'
        return is_enabled < 1 ? enabled_text + value : value
      },
      is_enabled_text(value, text) {
        let enabled_text=text?text:'未上线'
        return value < 1 ? enabled_text : ''
      },
      //多余字符省略,用法: {{scope.row.summary | ellipsis(14)}}
      ellipsis(value, length) {
        if (!value) return ''
        if (value.length > length) {
          return value.slice(0, length) + '...'
        }
        return value
      },
    },
    mounted() {
      this.correlationList('', (correlation_list) => {
        this.full_correlation_list = correlation_list
      })
    },
    methods: {
      //关联数据列表
      correlationList(keywords, callback){
        let params = {limit: 100}
        if(keywords){
          params.keywords = keywords
        }
        this.axiosGet('../ProjectAdmin/index?action',{params:params} ).then(response => {
          let data = response.data.data
          this.correlation_list = data.list
          if(!this.correlation_list.length){
            setTimeout(()=>{
              this.correlation_list=this.full_correlation_list
            },1000)
          }
          if(callback){
            callback(this.correlation_list)
          }
        })
      },
      //表单重置
      resetForm(formName){
        this.$refs[formName].resetFields()
        this.picture = ''
      },
      //空数据验证器(用于上传等非input控件)
      emptyValidator(rule, value, callback){
        if (!this.info[rule.field]) {
          callback(new Error());
        } else {
          callback();
        }
      },
      axiosRequest(config, handle_error = true) {
        var vm = this
        return new Promise((resolve, reject) => {
          config.method == 'post' ? vm.posting=true : vm.loading=true
          axios({timeout:20000,...config}).then(function(response){
            if(response.request.responseURL == 'https://operate.hinabian.com/index/index'){
              location.href = response.request.responseURL;
              return;
            }
            if(response.data.code != 'ok' && handle_error){
              vm.$notify.error(response.data.msg)
              reject(response)
            }
            else{
              config.method == 'post' ? vm.$notify.success('操作成功') : ''
              resolve(response)
            }
          }).catch(function (error) {
            if(error.response){
              console.warn('服务端错误',error.response.data.ref)
              data = error.response.data
              if(handle_error){
                if(data.msg){
                  vm.$notify.error(data.msg)
                } else{
                  vm.$notify.error(error.response.statusText)
                }
              }
            } else{
              console.warn('axios catch error',error)
              vm.$notify.error(error.message)
            }
            reject(error)
          }).finally(function () {
            config.method == 'post' ? vm.posting=false : vm.loading=false
          })
        })
      },
      axiosGet(url, config, handle_error = true){
        return this.axiosRequest({
          url:url,
          method:'get',
          ...config
        })
      },
      axiosPost(url, data, config, handle_error = true){
        return this.axiosRequest({
          url:url,
          method:'post',
          data,
          ...config
        })
      }
    }
  }
</script>