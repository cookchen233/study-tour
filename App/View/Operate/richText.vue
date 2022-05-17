<style scoped>
  .el-form-item--mini.el-form-item, .el-form-item--small.el-form-item {
    margin: 10px 0;
  }
</style>
<template>
  <el-form ref="dataForm" :rules="rules" :model="info" label-width="0">
    <el-form-item label="" prop="content"><textarea style="width: 90%;height: 100px;" :id="rich_text_id"></textarea></el-form-item>
  </el-form>
</template>
<script>
  module.exports = {
    props: ['info'],
    data() {
      return {
        rich_text_id:'t' + Math.floor(Math.random()*(10000-0+1)+0),
        correlation_list: this.info.uuid ? [this.info] : [],
        picture:'',
        country: [],
        state: [],
        rules: {
          content: [
            { validator:(rule,value,callback) =>{
                this.info.content = tinymce.get(this.rich_text_id).getContent()
                if(!this.info.content){
                  return callback('内容是必须的')
                }
                callback()
              }, required: true, trigger: 'blur'
            }
          ]
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
      this.tinymceInit(() => {
        if(this.info.content){
          tinymce.get(this.rich_text_id).setContent(this.info.content)
        }
      })
    },
    methods: {
      triggerSaveContent(){
        this.info.content = tinymce.get(this.rich_text_id).getContent()
      },
      tinymceInit(initCallback){
        tinymce.remove('#' + this.rich_text_id)
        tinymce.init({
          selector: '#' + this.rich_text_id,
          language: 'zh_CN',
          //width: 600,
          //height: 600,
          mobile: {
            theme: 'mobile',
          },
          //paste_webkit_styles: "color font-size",
          paste_remove_styles_if_webkit: false,
          plugins: [
            'advlist autolink link image lists charmap print preview hr anchor pagebreak spellchecker',
            'searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking',
            'save table directionality emoticons template powerpaste axupimgs',
            //'fullpage'
          ],
          powerpaste_word_import: 'propmt',// 参数可以是propmt, merge, clear，效果自行切换对比
          powerpaste_html_import: 'propmt',// propmt, merge, clear
          powerpaste_allow_local_images: true,
          paste_data_images: true,
          /*toolbar: 'insertfile undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | print preview media fullpage | forecolor backcolor emoticons hr'*/
          toolbar: 'insertfile undo redo | bold fontsizeselect forecolor link emoticons image axupimgs| hr bullist numlist outdent indent | code preview fullscreen',
          setup: function (editor) {
            console.log('Editor was setup.');
            editor.on('init', function (e) {
              console.log('Editor was initialized.');
              if(initCallback){
                initCallback();
              }
            });
          },
          images_upload_handler: function (blobInfo, success, failure) {
            var file = blobInfo.blob()
            var xhr, formData;
            xhr = new XMLHttpRequest();
            xhr.withCredentials = false;
            xhr.open('POST', '/file/image/saveWithOssLimit/size/50000');
            xhr.onload = function() {
              var json;
              if (xhr.status != 200) {
                failure('HTTP Error: ' + xhr.status);
                return;
              }
              json = JSON.parse(xhr.responseText);
              if (!json) {
                failure('Invalid JSON: ' + xhr.responseText);
                return;
              }
              if(json.errorCode != 0){
                failure('错误: ' + json.data);
              }

              success(json.data);
            };
            formData = new FormData();
            formData.append('file', file, file.name);
            xhr.send(formData);
          }
        })
      },
      //关联数据列表
      correlationList(query){
        let params = {
          limit: 20,
          keywords: query.trim()
        }
        if(!params.keywords || typeof query != 'string'){
          return
        }
        this.axiosGet('../ProjectAdmin/index?action',{params:params} ).then(response => {
          if(response.data.code != 'ok'){
            this.correlation_list = []
          }
          else{
            let data = response.data.data
            this.correlation_list = data.list
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